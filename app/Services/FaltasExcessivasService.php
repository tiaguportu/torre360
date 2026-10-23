<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MeioContatoFamilia;
use App\Enums\ResultadoContatoFamilia;
use App\Enums\SituacaoMatricula;
use App\Enums\StatusCasoFrequencia;
use App\Models\CasoFrequencia;
use App\Models\ConselhoTutelar;
use App\Models\ContatoCasoFrequencia;
use App\Models\Curso;
use App\Models\FrequenciaEscolar;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Faltas excessivas e comunicação ao Conselho Tutelar (Onda 57).
 *
 * A LDB manda notificar o Conselho Tutelar quando as faltas passam de 30% do percentual permitido em lei (art. 12, VIII):
 * com a frequência mínima de 75%, o permitido é 25% e 30% disso são 7,5% das aulas previstas no ano. O ECA (art. 56, II)
 * trata das faltas injustificadas e manda esgotar os recursos escolares antes. Por isso o fluxo tem o registro das
 * tentativas de contato com a família, e o sistema mostra as faltas totais e as injustificadas lado a lado.
 *
 * O sistema monta a relação e guarda o histórico; o envio é da direção.
 */
class FaltasExcessivasService
{
    public function __construct(private readonly CargaLetivaService $carga) {}

    /**
     * Percentual das aulas previstas no ano a partir do qual a escola notifica (7,5% com os padrões).
     */
    public function limitePercentual(): float
    {
        $permitido = 100 - (float) config('frequencia_legal.frequencia_minima', 75);

        return round($permitido * (float) config('frequencia_legal.notificacao.percentual_do_permitido', 30) / 100, 2);
    }

    /**
     * Alunos ativos com faltas acima do limite no período, do mais faltoso para o menos.
     *
     * @return Collection<int, array{matricula: Matricula, turma: Turma, aulas_previstas: int, faltas: int, justificadas: int, injustificadas: int, contadas: int, limite_aulas: int, percentual: float, caso: ?CasoFrequencia}>
     */
    public function acimaDoLimite(PeriodoLetivo $periodo, ?int $turmaId = null, ?CarbonInterface $hoje = null): Collection
    {
        $linhas = collect();
        $casos = CasoFrequencia::query()->abertos()->where('periodo_letivo_id', $periodo->id)->get()->keyBy('matricula_id');
        $criterio = config('frequencia_legal.notificacao.criterio', 'todas');

        $turmas = Turma::query()
            ->where('periodo_letivo_id', $periodo->id)
            ->where('status', '!=', 'cancelada')
            ->when($turmaId, fn ($consulta) => $consulta->whereKey($turmaId))
            ->orderBy('nome')
            ->get();

        foreach ($turmas as $turma) {
            $previstas = $this->carga->apurar($turma, $hoje)['aulas_previstas'];

            if ($previstas === 0) {
                continue;
            }

            $limite = (int) floor($previstas * $this->limitePercentual() / 100);
            $faltasPorAluno = $this->faltasPorAluno($turma, $periodo);

            if ($faltasPorAluno->isEmpty()) {
                continue;
            }

            $matriculas = Matricula::query()
                ->whereIn('id', $faltasPorAluno->keys())
                ->where('situacao', SituacaoMatricula::ATIVA->value)
                ->with('pessoa')
                ->get()
                ->keyBy('id');

            foreach ($faltasPorAluno as $matriculaId => $faltas) {
                $matricula = $matriculas->get($matriculaId);

                if (! $matricula) {
                    continue;
                }

                $contadas = $criterio === 'injustificadas' ? $faltas['faltas'] - $faltas['justificadas'] : $faltas['faltas'];

                if ($contadas <= $limite) {
                    continue;
                }

                $linhas->push([
                    'matricula' => $matricula,
                    'turma' => $turma,
                    'aulas_previstas' => $previstas,
                    'faltas' => $faltas['faltas'],
                    'justificadas' => $faltas['justificadas'],
                    'injustificadas' => $faltas['faltas'] - $faltas['justificadas'],
                    'contadas' => $contadas,
                    'limite_aulas' => $limite,
                    'percentual' => round($faltas['faltas'] / $previstas * 100, 2),
                    'caso' => $casos->get($matriculaId),
                ]);
            }
        }

        return $linhas->sortByDesc('faltas')->values();
    }

    /**
     * Abre o caso do aluno com a apuração de hoje. Só abre se o aluno realmente está acima do limite e não tem outro
     * caso em andamento.
     *
     * @throws \DomainException
     */
    public function abrirCaso(Matricula $matricula, PeriodoLetivo $periodo, ?User $por = null, ?string $observacao = null): CasoFrequencia
    {
        if (CasoFrequencia::query()->abertos()->where('matricula_id', $matricula->id)->exists()) {
            throw new \DomainException('Este aluno já tem um caso de faltas em andamento.');
        }

        $linha = $this->acimaDoLimite($periodo, $matricula->turma_id)->first(fn (array $l): bool => $l['matricula']->id === $matricula->id);

        if (! $linha) {
            throw new \DomainException('O aluno não está acima do limite de faltas neste período.');
        }

        $caso = CasoFrequencia::create([
            'matricula_id' => $matricula->id,
            'periodo_letivo_id' => $periodo->id,
            'status' => StatusCasoFrequencia::Aberto,
            'aulas_previstas' => $linha['aulas_previstas'],
            'faltas' => $linha['faltas'],
            'faltas_justificadas' => $linha['justificadas'],
            'limite_aulas' => $linha['limite_aulas'],
            'percentual_faltas' => $linha['percentual'],
            'conselho_tutelar_id' => ConselhoTutelar::paraUnidade($this->unidadeDaTurma($linha['turma']))?->id,
            'observacao' => $observacao,
            'aberto_por_user_id' => $por?->id,
        ]);

        $this->registrarLog($caso, $por, 'Caso de faltas excessivas aberto.', ['faltas' => $linha['faltas'], 'limite' => $linha['limite_aulas']]);

        return $caso;
    }

    /**
     * Refaz a apuração de um caso aberto (depois de justificar faltas, por exemplo). Os números do ofício saem dele.
     *
     * @throws \DomainException
     */
    public function atualizarApuracao(CasoFrequencia $caso, ?User $por = null): CasoFrequencia
    {
        $caso = CasoFrequencia::query()->findOrFail($caso->id);
        $this->exigirAberto($caso);

        $matricula = Matricula::query()->findOrFail($caso->matricula_id);
        $periodo = PeriodoLetivo::query()->find($caso->periodo_letivo_id);
        $linha = $periodo ? $this->acimaDoLimite($periodo, $matricula->turma_id)->first(fn (array $l): bool => $l['matricula']->id === $matricula->id) : null;

        // Já não passa do limite (faltas justificadas depois): os números do caso seguem os de hoje mesmo assim.
        $turma = Turma::query()->findOrFail($matricula->turma_id);
        $previstas = $this->carga->apurar($turma)['aulas_previstas'];
        $faltas = $linha['faltas'] ?? $this->faltasDoAluno($matricula, $periodo)['faltas'];
        $justificadas = $linha['justificadas'] ?? $this->faltasDoAluno($matricula, $periodo)['justificadas'];

        $caso->update([
            'aulas_previstas' => $previstas,
            'faltas' => $faltas,
            'faltas_justificadas' => $justificadas,
            'limite_aulas' => (int) floor($previstas * $this->limitePercentual() / 100),
            'percentual_faltas' => $previstas > 0 ? round($faltas / $previstas * 100, 2) : 0,
        ]);

        $this->registrarLog($caso, $por, 'Apuração do caso atualizada.', ['faltas' => $faltas]);

        return $caso->refresh();
    }

    /**
     * @throws \DomainException
     */
    public function registrarContato(CasoFrequencia $caso, CarbonInterface $data, MeioContatoFamilia $meio, ResultadoContatoFamilia $resultado, ?string $observacao = null, ?User $por = null): ContatoCasoFrequencia
    {
        $caso = CasoFrequencia::query()->findOrFail($caso->id);
        $this->exigirAberto($caso);

        if ($data->gt(today())) {
            throw new \DomainException('A data do contato não pode ser no futuro.');
        }

        $contato = ContatoCasoFrequencia::create([
            'caso_frequencia_id' => $caso->id,
            'data' => $data->toDateString(),
            'meio' => $meio,
            'resultado' => $resultado,
            'observacao' => filled($observacao) ? mb_substr(trim($observacao), 0, 500) : null,
            'registrado_por_user_id' => $por?->id,
        ]);

        if ($caso->status === StatusCasoFrequencia::Aberto) {
            $caso->update(['status' => StatusCasoFrequencia::EmContato]);
        }

        // O que a família disse fica só no registro do contato, cifrado; o histórico leva o meio e o resultado.
        $this->registrarLog($caso, $por, 'Contato com a família registrado.', ['meio' => $meio->value, 'resultado' => $resultado->value]);

        return $contato;
    }

    /**
     * Registra que o ofício foi enviado ao Conselho Tutelar.
     *
     * @throws \DomainException
     */
    public function marcarNotificado(CasoFrequencia $caso, CarbonInterface $quando, ?string $protocolo = null, ?ConselhoTutelar $conselho = null, ?User $por = null): CasoFrequencia
    {
        $caso = CasoFrequencia::query()->findOrFail($caso->id);
        $this->exigirAberto($caso);

        if ($caso->foiNotificado()) {
            throw new \DomainException('O Conselho Tutelar já foi notificado neste caso.');
        }

        if ($quando->gt(now())) {
            throw new \DomainException('A data do envio não pode ser no futuro.');
        }

        if (config('frequencia_legal.notificacao.exigir_contato_antes_do_oficio', true) && ! $caso->contatos()->exists()) {
            throw new \DomainException('Registre ao menos uma tentativa de contato com a família antes de comunicar o Conselho Tutelar: é a prova de que os recursos da escola foram esgotados.');
        }

        $conselho ??= $caso->conselho_tutelar_id ? ConselhoTutelar::query()->find($caso->conselho_tutelar_id) : null;

        $caso->update([
            'status' => StatusCasoFrequencia::Notificado,
            'notificado_em' => $quando,
            'protocolo_externo' => filled($protocolo) ? mb_substr(trim($protocolo), 0, 80) : null,
            'conselho_tutelar_id' => $conselho?->id,
            'notificado_por_user_id' => $por?->id,
        ]);

        $this->registrarLog($caso, $por, 'Conselho Tutelar notificado.', ['conselho_tutelar_id' => $conselho?->id]);

        return $caso->refresh();
    }

    /**
     * @throws \DomainException
     */
    public function registrarResposta(CasoFrequencia $caso, string $resposta, CarbonInterface $quando, ?User $por = null): CasoFrequencia
    {
        $caso = CasoFrequencia::query()->findOrFail($caso->id);
        $this->exigirAberto($caso);

        if (! $caso->foiNotificado()) {
            throw new \DomainException('Só há resposta do Conselho depois de registrar o envio do ofício.');
        }

        if (trim($resposta) === '') {
            throw new \DomainException('Escreva a resposta do Conselho Tutelar.');
        }

        $caso->update(['resposta' => trim($resposta), 'respondido_em' => $quando]);

        $this->registrarLog($caso, $por, 'Resposta do Conselho Tutelar registrada.');

        return $caso->refresh();
    }

    /**
     * @throws \DomainException
     */
    public function encerrar(CasoFrequencia $caso, string $motivo, ?User $por = null): CasoFrequencia
    {
        $caso = CasoFrequencia::query()->findOrFail($caso->id);
        $this->exigirAberto($caso);

        if (trim($motivo) === '') {
            throw new \DomainException('Informe o motivo do encerramento (frequência regularizada, aluno transferido, resposta do Conselho...).');
        }

        $caso->update([
            'status' => StatusCasoFrequencia::Encerrado,
            'encerrado_em' => now(),
            'encerramento_motivo' => mb_substr(trim($motivo), 0, 160),
            'encerrado_por_user_id' => $por?->id,
        ]);

        $this->registrarLog($caso, $por, 'Caso de faltas encerrado.', ['motivo' => $motivo]);

        return $caso->refresh();
    }

    /**
     * Marca como justificadas as faltas escolhidas do aluno (atestado, por exemplo).
     *
     * @param  list<int>  $frequenciaIds
     * @return int quantas faltas foram justificadas
     *
     * @throws \DomainException
     */
    public function justificarFaltas(Matricula $matricula, array $frequenciaIds, string $justificativa, ?User $por = null): int
    {
        $justificativa = trim($justificativa);

        if ($justificativa === '') {
            throw new \DomainException('Informe a justificativa (atestado médico, óbito na família...).');
        }

        if ($frequenciaIds === []) {
            throw new \DomainException('Escolha ao menos uma falta.');
        }

        $total = FrequenciaEscolar::query()
            ->where('matricula_id', $matricula->id)
            ->whereIn('id', $frequenciaIds)
            ->where('situacao', 'ausente')
            ->update(['justificada' => true, 'justificativa' => mb_substr($justificativa, 0, 160)]);

        activity('frequencia')
            ->performedOn($matricula)
            ->causedBy($por)
            ->withProperties(['faltas_justificadas' => $total])
            ->log('Faltas justificadas.');

        return $total;
    }

    /**
     * Faltas de cada aluno da turma no período: matrícula => [faltas, justificadas].
     *
     * @return Collection<int, array{faltas: int, justificadas: int}>
     */
    private function faltasPorAluno(Turma $turma, PeriodoLetivo $periodo): Collection
    {
        $frequencias = (new FrequenciaEscolar)->getTable();
        $aulas = 'cronograma_aula';

        return FrequenciaEscolar::query()
            ->join($aulas, "{$aulas}.id", '=', "{$frequencias}.cronograma_aula_id")
            ->where("{$aulas}.turma_id", $turma->id)
            ->whereDate("{$aulas}.data", '>=', $periodo->data_inicio)
            ->whereDate("{$aulas}.data", '<=', $periodo->data_fim)
            ->whereNull("{$aulas}.nao_realizada_em")
            ->where("{$frequencias}.situacao", 'ausente')
            ->groupBy("{$frequencias}.matricula_id")
            ->selectRaw("{$frequencias}.matricula_id as matricula_id")
            ->selectRaw('count(*) as faltas')
            ->selectRaw("sum(case when {$frequencias}.justificada = ? then 1 else 0 end) as justificadas", [true])
            ->get()
            ->mapWithKeys(fn ($linha): array => [(int) $linha->matricula_id => ['faltas' => (int) $linha->faltas, 'justificadas' => (int) $linha->justificadas]]);
    }

    /**
     * @return array{faltas: int, justificadas: int}
     */
    private function faltasDoAluno(Matricula $matricula, ?PeriodoLetivo $periodo): array
    {
        $turma = Turma::query()->find($matricula->turma_id);

        if (! $turma || ! $periodo) {
            return ['faltas' => 0, 'justificadas' => 0];
        }

        return $this->faltasPorAluno($turma, $periodo)->get($matricula->id, ['faltas' => 0, 'justificadas' => 0]);
    }

    private function unidadeDaTurma(Turma $turma): ?int
    {
        $cursoId = $turma->serie_id ? Serie::query()->whereKey($turma->serie_id)->value('curso_id') : null;

        return $cursoId ? Curso::query()->whereKey($cursoId)->value('unidade_id') : null;
    }

    /**
     * @throws \DomainException
     */
    private function exigirAberto(CasoFrequencia $caso): void
    {
        if (! $caso->estaAberto()) {
            throw new \DomainException('Este caso já foi encerrado.');
        }
    }

    /**
     * @param  array<string, mixed>  $propriedades
     */
    private function registrarLog(CasoFrequencia $caso, ?User $por, string $mensagem, array $propriedades = []): void
    {
        $registro = activity('frequencia')
            ->performedOn($caso)
            ->withProperties(['matricula_id' => $caso->matricula_id, 'status' => $caso->status->value] + $propriedades);

        if ($por) {
            $registro->causedBy($por);
        }

        $registro->log($mensagem);
    }
}

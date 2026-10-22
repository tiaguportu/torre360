<?php

declare(strict_types=1);

namespace App\Services\Educacenso;

use App\Enums\SituacaoAlunoCenso;
use App\Enums\SituacaoFinal;
use App\Enums\SituacaoMatricula;
use App\Enums\StatusTransferencia;
use App\Enums\TipoTransferencia;
use App\Exceptions\CensoEscolarException;
use App\Models\CensoSituacaoAluno;
use App\Models\FrequenciaEscolar;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\SituacaoFinalDisciplina;
use App\Models\TransferenciaEscolar;
use App\Models\Turma;
use App\Models\Unidade;
use App\Models\User;
use App\Services\FrequenciaAlunoService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Apura a situação de cada aluno de uma escola para a 2ª etapa do Censo Escolar (Situação do Aluno).
 *
 * Ordem do cálculo: ajuste manual da secretaria > transferência de saída concluída (1) > evasão (2) > cancelada ou
 * trancada sem transferência (2, com aviso) > Educação Infantil (7, sem movimentação) > resultado final por disciplina
 * (reprovado 4; aprovado 5; aprovado concluinte 6 na etapa final). Depois valida a situação contra as regras de etapa
 * do leiaute (versão 3 do Censo 2025).
 *
 * Registro de cada matrícula: 90 se tem o código da matrícula do Inep (veio da matrícula inicial); 91 se não tem e
 * começou depois da data de referência do Censo (admitido após); sem registro (pendência) se começou antes e ainda
 * não tem o código, porque falta importar o retorno da 1ª etapa.
 */
class SituacaoAlunoCensoService
{
    /** Situações de matrícula que entram no Censo mesmo sem código do Inep. */
    private const SITUACOES_DO_CENSO = [
        SituacaoMatricula::ATIVA,
        SituacaoMatricula::CONCLUIDA,
        SituacaoMatricula::TRANCADA,
        SituacaoMatricula::CANCELADA,
        SituacaoMatricula::EVASAO,
    ];

    /**
     * Data de referência do Censo Escolar: a última quarta-feira de maio do ano. Pode ser fixada por ano em
     * `config('educacenso.data_referencia')` (formato Y-m-d), se o Inep mudar.
     */
    public function dataReferencia(int $ano): CarbonImmutable
    {
        $configurada = config("educacenso.data_referencia.{$ano}");

        if (is_string($configurada) && $configurada !== '') {
            return CarbonImmutable::parse($configurada)->startOfDay();
        }

        $dia = CarbonImmutable::create($ano, 5, 31)->startOfDay();

        while ($dia->dayOfWeek !== CarbonInterface::WEDNESDAY) {
            $dia = $dia->subDay();
        }

        return $dia;
    }

    public function anoDoCenso(PeriodoLetivo $periodo): int
    {
        return (int) ($periodo->data_inicio?->year ?? (int) $periodo->nome);
    }

    /**
     * Matrículas que entram na apuração: das turmas da escola no período, nas situações do Censo ou com código do Inep.
     *
     * @return Builder<Matricula>
     */
    public function consulta(Unidade $unidade, PeriodoLetivo $periodo): Builder
    {
        return Matricula::query()
            ->whereIn('turma_id', $this->turmasDaEscola($unidade, $periodo)->select('id'))
            ->where(fn (Builder $q) => $q
                ->whereIn('situacao', array_map(fn (SituacaoMatricula $s): string => $s->value, self::SITUACOES_DO_CENSO))
                ->orWhereNotNull('codigo_matricula_inep'));
    }

    /**
     * @return Builder<Turma>
     */
    public function turmasDaEscola(Unidade $unidade, PeriodoLetivo $periodo): Builder
    {
        return Turma::query()
            ->where('periodo_letivo_id', $periodo->id)
            ->whereHas('serie.curso', fn (Builder $q) => $q->where('unidade_id', $unidade->id));
    }

    /**
     * Dados da escola para o registro 89 e o que impede de gerar o arquivo.
     *
     * @return array{inep: string, gestor_cpf: string, gestor_nome: string, pendencias: list<string>}
     */
    public function escola(Unidade $unidade): array
    {
        $unidade->loadMissing(['instituicaoEnsino', 'representantesLegais']);

        $inep = FormatoInep::digitos($unidade->codigo_inep ?: $unidade->instituicaoEnsino?->codigo_inep);
        $gestor = $unidade->representantesLegais->first(fn (Pessoa $p): bool => strlen(FormatoInep::digitos($p->cpf)) === 11)
            ?? $unidade->representantesLegais->first();
        $cpf = FormatoInep::digitos($gestor?->cpf);
        $pendencias = [];

        if (strlen($inep) !== 8) {
            $pendencias[] = 'A unidade não tem o código Inep da escola (8 dígitos). Cadastre-o em Unidades ou Instituições de Ensino.';
        }

        if (! $gestor) {
            $pendencias[] = 'A unidade não tem gestor (representante legal) cadastrado: o registro 89 pede o CPF e o nome dele.';
        } elseif (strlen($cpf) !== 11) {
            $pendencias[] = "O gestor {$gestor->nome} está sem CPF válido.";
        }

        return [
            'inep' => $inep,
            'gestor_cpf' => $cpf,
            'gestor_nome' => FormatoInep::letras($gestor?->nome),
            'pendencias' => $pendencias,
        ];
    }

    /**
     * Uma linha por matrícula, com o registro (90/91), a situação, o motivo e o que impede de enviar.
     *
     * @return Collection<int, array{matricula: Matricula, registro: ?int, situacao: ?SituacaoAlunoCenso, origem: string, motivo: string, etapa: string, pendencias: list<string>, avisos: list<string>}>
     */
    public function apurar(Unidade $unidade, PeriodoLetivo $periodo): Collection
    {
        $matriculas = $this->consulta($unidade, $periodo)
            ->with(['pessoa', 'turma.etapaEnsino'])
            ->get();

        return $this->linhas($matriculas, $periodo);
    }

    /**
     * @param  Collection<int, Matricula>  $matriculas
     * @return Collection<int, array{matricula: Matricula, registro: ?int, situacao: ?SituacaoAlunoCenso, origem: string, motivo: string, etapa: string, pendencias: list<string>, avisos: list<string>}>
     */
    public function linhas(Collection $matriculas, PeriodoLetivo $periodo): Collection
    {
        $referencia = $this->dataReferencia($this->anoDoCenso($periodo));

        // Quem saiu antes da data de referência e nunca recebeu código do Inep não estava na matrícula inicial: não entra.
        $matriculas = $matriculas->reject(fn (Matricula $m): bool => $this->saiuAntesDaReferencia($m, $referencia))->values();
        $matriculas->loadMissing(['pessoa', 'turma.etapaEnsino']);
        $ids = $matriculas->pluck('id');

        $ajustes = CensoSituacaoAluno::query()->whereIn('matricula_id', $ids)->get()->keyBy('matricula_id');
        $transferencias = TransferenciaEscolar::query()
            ->whereIn('matricula_id', $ids)
            ->where('tipo', TipoTransferencia::Saida->value)
            ->where('status', StatusTransferencia::Concluida->value)
            ->orderBy('data')
            ->get()
            ->keyBy('matricula_id');
        $finais = SituacaoFinalDisciplina::query()
            ->whereIn('matricula_id', $ids)
            ->where('periodo_letivo_id', $periodo->id)
            ->get()
            ->groupBy('matricula_id');
        $frequencias = $this->frequencias($ids);

        return $matriculas
            ->map(fn (Matricula $m): array => $this->linha($m, $referencia, $ajustes->get($m->id), $transferencias->get($m->id), $finais->get($m->id, collect()), $frequencias[$m->id] ?? null))
            ->sortBy(fn (array $l): string => mb_strtolower(($l['matricula']->turma?->nome ?? '').' '.($l['matricula']->pessoa?->nome ?? '')))
            ->values();
    }

    /**
     * @param  Collection<int, SituacaoFinalDisciplina>  $finais
     * @return array{matricula: Matricula, registro: ?int, situacao: ?SituacaoAlunoCenso, origem: string, motivo: string, etapa: string, pendencias: list<string>, avisos: list<string>}
     */
    private function linha(Matricula $matricula, CarbonImmutable $referencia, ?CensoSituacaoAluno $ajuste, ?TransferenciaEscolar $transferencia, Collection $finais, ?float $frequencia): array
    {
        $turma = $matricula->turma;
        $etapa = (string) ($turma?->etapaEnsino?->codigo ?? '');
        $pendencias = [];
        $avisos = [];

        [$situacao, $motivo] = $this->situacaoCalculada($matricula, $etapa, $transferencia, $finais, $frequencia, $pendencias, $avisos);
        $origem = 'calculada';

        if ($ajuste) {
            $avisos[] = 'Cálculo do sistema: '.($situacao?->getLabel() ?? 'sem resultado').'. Vale o ajuste da secretaria.';
            $situacao = $ajuste->situacao;
            $motivo = 'Ajuste manual: '.$ajuste->justificativa;
            $origem = 'ajuste';
            $pendencias = array_values(array_filter($pendencias, fn (string $p): bool => ! str_starts_with($p, 'Resultado')));
        }

        if ($situacao && ($restricao = $situacao->restricaoParaEtapa($etapa))) {
            $pendencias[] = $restricao;
        }

        if ($etapa === '') {
            $avisos[] = 'A turma não tem etapa de ensino do Educacenso: as regras de etapa não foram conferidas.';
        }

        if (strlen(FormatoInep::digitos($matricula->pessoa?->codigo_inep)) !== 12) {
            $pendencias[] = 'Aluno sem ID Inep: importe o retorno da matrícula inicial ou, se ele entrou depois, o resultado da identificação.';
        }

        $registro = $this->registro($matricula, $referencia);

        if ($registro === null) {
            $pendencias[] = 'Matrícula sem o código do Inep e iniciada antes da data de referência ('.$referencia->format('d/m/Y').'): importe o retorno da matrícula inicial.';
        } elseif ($registro === 90 && FormatoInep::digitos($turma?->codigo_inep) === '') {
            $pendencias[] = 'Turma sem código Inep: importe o retorno da matrícula inicial.';
        } elseif ($registro === 91 && FormatoInep::digitos($turma?->codigo_inep) === '' && $etapa === '') {
            $pendencias[] = 'Aluno admitido após a data de referência numa turma sem código Inep e sem etapa: informe a etapa da turma.';
        } elseif ($registro === 91 && in_array($etapa, SituacaoAlunoCenso::ETAPAS_MULTI, true)) {
            $pendencias[] = 'Turma com mais de uma etapa: o registro 91 pede a etapa do aluno, que deve ser informada direto no Educacenso.';
        }

        if ($situacao === null && $pendencias === []) {
            $pendencias[] = 'Sem situação definida: ajuste manualmente.';
        }

        return [
            'matricula' => $matricula,
            'registro' => $registro,
            'situacao' => $situacao,
            'origem' => $origem,
            'motivo' => $motivo,
            'etapa' => $etapa,
            'pendencias' => array_values(array_unique($pendencias)),
            'avisos' => array_values(array_unique($avisos)),
        ];
    }

    /**
     * @param  Collection<int, SituacaoFinalDisciplina>  $finais
     * @param  list<string>  $pendencias
     * @param  list<string>  $avisos
     * @return array{0: ?SituacaoAlunoCenso, 1: string}
     */
    private function situacaoCalculada(Matricula $matricula, string $etapa, ?TransferenciaEscolar $transferencia, Collection $finais, ?float $frequencia, array &$pendencias, array &$avisos): array
    {
        if ($transferencia) {
            return [SituacaoAlunoCenso::Transferido, 'Transferência de saída concluída em '.($transferencia->data?->format('d/m/Y') ?? 'data não informada').'.'];
        }

        $situacaoMatricula = $matricula->situacao;

        if ($situacaoMatricula === SituacaoMatricula::EVASAO) {
            return [SituacaoAlunoCenso::DeixouDeFrequentar, 'Matrícula com situação "Evasão".'];
        }

        if (in_array($situacaoMatricula, [SituacaoMatricula::CANCELADA, SituacaoMatricula::TRANCADA], true)) {
            $avisos[] = 'Matrícula '.mb_strtolower($situacaoMatricula->getLabel()).' sem transferência registrada: confira se o aluno deixou de frequentar ou foi transferido.';

            return [SituacaoAlunoCenso::DeixouDeFrequentar, 'Matrícula '.mb_strtolower($situacaoMatricula->getLabel()).' sem transferência.'];
        }

        if (in_array($etapa, SituacaoAlunoCenso::ETAPAS_EDUCACAO_INFANTIL, true)) {
            return [SituacaoAlunoCenso::SemMovimentacao, 'Educação Infantil: permaneceu na escola até o fim do ano.'];
        }

        if ($finais->isEmpty()) {
            $pendencias[] = 'Resultado final não calculado: feche o ano no boletim ou ajuste manualmente.';

            return [null, 'Sem resultado final.'];
        }

        $efetivas = $finais->map(fn (SituacaoFinalDisciplina $f): ?SituacaoFinal => $f->situacao_final_pos_exame ?? $f->situacao);
        $reprovadas = $efetivas->filter(fn (?SituacaoFinal $s): bool => $s === SituacaoFinal::REPROVADO)->count();

        if ($reprovadas > 0) {
            return [SituacaoAlunoCenso::Reprovado, "Reprovado em {$reprovadas} disciplina(s)."];
        }

        if ($efetivas->contains(fn (?SituacaoFinal $s): bool => $s !== SituacaoFinal::APROVADO)) {
            $pendencias[] = 'Resultado final em aberto: há disciplina em recuperação ou sem resultado.';

            return [null, 'Resultado final em aberto.'];
        }

        if ($frequencia !== null && $frequencia < FrequenciaAlunoService::FREQUENCIA_MINIMA) {
            $avisos[] = 'Frequência de '.number_format($frequencia, 1, ',', '.').'% (abaixo de 75%): confira se a reprovação por falta foi considerada.';
        }

        if (in_array($etapa, SituacaoAlunoCenso::ETAPAS_FINAIS, true)) {
            return [SituacaoAlunoCenso::AprovadoConcluinte, 'Aprovado em todas as disciplinas na etapa final do curso.'];
        }

        return [SituacaoAlunoCenso::Aprovado, 'Aprovado em todas as disciplinas.'];
    }

    private function saiuAntesDaReferencia(Matricula $matricula, CarbonImmutable $referencia): bool
    {
        if (FormatoInep::digitos($matricula->codigo_matricula_inep) !== '' || ! $matricula->data_desativacao) {
            return false;
        }

        return $matricula->data_desativacao->copy()->startOfDay()->lte($referencia);
    }

    private function registro(Matricula $matricula, CarbonImmutable $referencia): ?int
    {
        if (FormatoInep::digitos($matricula->codigo_matricula_inep) !== '') {
            return 90;
        }

        $inicio = $matricula->data_ativacao ?? $matricula->created_at;

        return $inicio && $inicio->copy()->startOfDay()->gt($referencia) ? 91 : null;
    }

    /**
     * Percentual de presença de cada matrícula nas aulas lançadas.
     *
     * @param  Collection<int, int>  $ids
     * @return array<int, float>
     */
    private function frequencias(Collection $ids): array
    {
        if ($ids->isEmpty()) {
            return [];
        }

        return FrequenciaEscolar::query()
            ->whereIn('matricula_id', $ids)
            ->whereIn('situacao', ['presente', 'ausente'])
            ->selectRaw('matricula_id, count(*) as total, sum(case when situacao = ? then 1 else 0 end) as presencas', ['presente'])
            ->groupBy('matricula_id')
            ->get()
            ->mapWithKeys(fn ($l): array => [(int) $l->matricula_id => (int) $l->total > 0 ? round((int) $l->presencas / (int) $l->total * 100, 1) : 100.0])
            ->all();
    }

    /**
     * Fixa a situação do aluno por decisão da secretaria. Vale no lugar do cálculo e respeita as regras de etapa do leiaute.
     *
     * @throws CensoEscolarException
     */
    public function definirAjuste(Matricula $matricula, SituacaoAlunoCenso $situacao, string $justificativa, ?User $por): CensoSituacaoAluno
    {
        $justificativa = trim($justificativa);

        if (mb_strlen($justificativa) < 10) {
            throw new CensoEscolarException('Explique o ajuste em pelo menos 10 caracteres.');
        }

        $matricula->loadMissing('turma.etapaEnsino');

        if ($restricao = $situacao->restricaoParaEtapa((string) ($matricula->turma?->etapaEnsino?->codigo ?? ''))) {
            throw new CensoEscolarException($restricao);
        }

        return CensoSituacaoAluno::query()->updateOrCreate(
            ['matricula_id' => $matricula->id],
            ['situacao' => $situacao, 'justificativa' => $justificativa, 'definido_por_user_id' => $por?->id],
        );
    }

    public function removerAjuste(Matricula $matricula): void
    {
        CensoSituacaoAluno::query()->where('matricula_id', $matricula->id)->get()->each->delete();
    }

    /**
     * Grava à mão os códigos do Inep (quando o retorno não os trouxe ou veio como "provável"). Vazio apaga o código.
     *
     * @throws CensoEscolarException
     */
    public function informarCodigos(Matricula $matricula, ?string $idInepAluno, ?string $codigoInepTurma, ?string $codigoMatricula, ?User $por): void
    {
        $id = FormatoInep::digitos($idInepAluno);
        $turmaInep = FormatoInep::digitos($codigoInepTurma);
        $codigo = FormatoInep::digitos($codigoMatricula);

        if ($id !== '' && strlen($id) !== 12) {
            throw new CensoEscolarException('O ID Inep do aluno tem 12 dígitos.');
        }

        if (strlen($turmaInep) > 10) {
            throw new CensoEscolarException('O código Inep da turma tem até 10 dígitos.');
        }

        if (strlen($codigo) > 12) {
            throw new CensoEscolarException('O código da matrícula tem até 12 dígitos.');
        }

        $matricula->loadMissing(['pessoa', 'turma']);

        if ($id !== '' && Pessoa::query()->where('codigo_inep', $id)->whereKeyNot($matricula->pessoa_id)->exists()) {
            throw new CensoEscolarException('Este ID Inep já está cadastrado para outro aluno.');
        }

        $matricula->pessoa->codigo_inep = $id !== '' ? $id : null;
        $matricula->pessoa->saveQuietly();

        if ($matricula->turma) {
            $matricula->turma->codigo_inep = $turmaInep !== '' ? $turmaInep : null;
            $matricula->turma->saveQuietly();
        }

        $matricula->codigo_matricula_inep = $codigo !== '' ? $codigo : null;
        $matricula->saveQuietly();

        activity('censo_escolar')
            ->causedBy($por)
            ->performedOn($matricula)
            ->log('Informou os códigos do Inep da matrícula');
    }

    /**
     * Resumo para os cartões da tela.
     *
     * @param  Collection<int, array<string, mixed>>  $linhas
     * @return array{total: int, prontas: int, registro_90: int, registro_91: int, com_pendencia: int, sem_id_inep: int, sem_codigo_matricula: int, ajustes: int}
     */
    public function resumo(Collection $linhas): array
    {
        return [
            'total' => $linhas->count(),
            'prontas' => $linhas->filter(fn (array $l): bool => $l['pendencias'] === [])->count(),
            'registro_90' => $linhas->where('registro', 90)->count(),
            'registro_91' => $linhas->where('registro', 91)->count(),
            'com_pendencia' => $linhas->filter(fn (array $l): bool => $l['pendencias'] !== [])->count(),
            'sem_id_inep' => $linhas->filter(fn (array $l): bool => strlen(FormatoInep::digitos($l['matricula']->pessoa?->codigo_inep)) !== 12)->count(),
            'sem_codigo_matricula' => $linhas->filter(fn (array $l): bool => $l['registro'] === null)->count(),
            'ajustes' => $linhas->where('origem', 'ajuste')->count(),
        ];
    }
}

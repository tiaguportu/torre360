<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SituacaoMatricula;
use App\Exceptions\ReuniaoInvalidaException;
use App\Models\Coordenador;
use App\Models\CronogramaAula;
use App\Models\EtapaAvaliativa;
use App\Models\Matricula;
use App\Models\OcorrenciaEscolar;
use App\Models\ParecerDescritivo;
use App\Models\Reuniao;
use App\Models\ReuniaoAluno;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * O lado "aluno por aluno" do conselho de classe (Onda 27): monta a pauta com a fotografia dos indicadores (nota, frequência,
 * ocorrências, parecer, risco de evasão) e registra a deliberação da turma. A deliberação só registra o que ficou decidido:
 * nunca altera nota, frequência nem a situação da matrícula.
 */
class ConselhoClasseService
{
    public function __construct(
        private readonly BoletimService $boletim,
        private readonly ReuniaoService $reunioes,
    ) {}

    /**
     * Uma linha por aluno ativo da turma, em ordem alfabética, com a fotografia dos indicadores de hoje. Chamar de novo
     * acrescenta quem entrou depois e atualiza a fotografia de quem ainda não foi deliberado.
     *
     * @return int quantidade de alunos na pauta
     */
    public function montarPauta(Reuniao $reuniao): int
    {
        if (! $reuniao->ehConselho() || ! $reuniao->turma_id || ! $reuniao->etapa_avaliativa_id) {
            throw ReuniaoInvalidaException::com('Só o conselho de classe, com turma e etapa definidas, tem pauta por aluno.');
        }

        if ($reuniao->ataEstaFechada() || $reuniao->estaCancelada()) {
            throw ReuniaoInvalidaException::com('A pauta não pode mais mudar: a reunião foi cancelada ou a ata já está fechada.');
        }

        $etapa = EtapaAvaliativa::query()->findOrFail($reuniao->etapa_avaliativa_id);

        $matriculas = Matricula::query()
            ->where('turma_id', $reuniao->turma_id)
            ->where('situacao', SituacaoMatricula::ATIVA->value)
            ->with('pessoa')
            ->get()
            ->sortBy(fn (Matricula $m) => mb_strtolower((string) $m->pessoa?->nome))
            ->values();

        DB::transaction(function () use ($reuniao, $matriculas, $etapa): void {
            $existentes = ReuniaoAluno::query()->where('reuniao_id', $reuniao->id)->get()->keyBy('matricula_id');

            foreach ($matriculas as $ordem => $matricula) {
                $linha = $existentes->get($matricula->id);

                if ($linha === null) {
                    ReuniaoAluno::create([
                        'reuniao_id' => $reuniao->id,
                        'matricula_id' => $matricula->id,
                        'ordem' => $ordem + 1,
                        'indicadores' => $this->indicadores($matricula, $etapa),
                    ]);

                    continue;
                }

                $dados = ['ordem' => $ordem + 1];

                if (! $linha->foiDeliberado()) {
                    $dados['indicadores'] = $this->indicadores($matricula, $etapa);
                }

                $linha->update($dados);
            }
        });

        return $matriculas->count();
    }

    /**
     * A fotografia dos indicadores do aluno na etapa. Fica gravada na pauta: a ata diz o que o conselho viu naquele dia, mesmo que
     * a nota mude depois.
     *
     * @return array<string, mixed>
     */
    public function indicadores(Matricula $matricula, EtapaAvaliativa $etapa): array
    {
        $matricula->loadMissing('turma.periodoLetivo');

        $notaAprovacao = (float) ($matricula->turma?->periodoLetivo?->nota_aprovacao ?? 0);
        $linhas = collect($this->boletim->getDadosBoletim($matricula, $etapa->id)['etapas'][0]['linhas'] ?? []);

        $medias = $linhas->pluck('media_final')->filter(fn ($media) => $media !== null);
        $abaixo = $linhas
            ->filter(fn (array $linha) => $linha['media_final'] !== null && (float) $linha['media_final'] < $notaAprovacao)
            ->map(fn (array $linha) => $linha['disciplina']->nome)
            ->values()
            ->all();

        $aulas = (int) $linhas->sum('total_aulas');
        $presencas = (int) $linhas->sum('presencas_count');

        $desde = now()->subDays((int) config('reunioes.janela_ocorrencias_dias', 120));
        $ocorrencias = OcorrenciaEscolar::query()
            ->where('matricula_id', $matricula->id)
            ->where('data_hora', '>=', $desde)
            ->with('tipoOcorrencia')
            ->get();

        return [
            'nota_aprovacao' => $notaAprovacao,
            'disciplinas' => $linhas->count(),
            'disciplinas_com_nota' => $medias->count(),
            'media_geral' => $medias->isEmpty() ? null : round((float) $medias->avg(), 1),
            'disciplinas_abaixo_da_media' => $abaixo,
            'frequencia' => $aulas > 0 ? round($presencas / $aulas * 100, 1) : null,
            'faltas' => max($aulas - $presencas, 0),
            'ocorrencias' => $ocorrencias->count(),
            'ocorrencias_tipos' => $ocorrencias->map(fn (OcorrenciaEscolar $o) => $o->tipoOcorrencia?->nome)->filter()->unique()->values()->take(5)->all(),
            'parecer_publicado' => ParecerDescritivo::query()
                ->publicados()
                ->where('matricula_id', $matricula->id)
                ->where('etapa_avaliativa_id', $etapa->id)
                ->exists(),
            'risco_evasao' => $matricula->risco_evasao_score !== null ? (int) $matricula->risco_evasao_score : null,
            'fotografado_em' => now()->toIso8601String(),
        ];
    }

    /**
     * Registra a deliberação sobre o aluno. Nas deliberações que pedem acompanhamento (`config('reunioes.deliberacoes_com_encaminhamento')`)
     * o encaminhamento nasce junto, com responsável e prazo; deliberar de novo troca a decisão e o encaminhamento ainda aberto.
     *
     * @param  array{descricao?: ?string, responsavel_user_id?: int|string|null, prazo?: mixed}|null  $encaminhamento
     */
    public function deliberar(ReuniaoAluno $linha, string $deliberacao, ?string $observacao, User $por, ?array $encaminhamento = null): ReuniaoAluno
    {
        $reuniao = Reuniao::query()->findOrFail($linha->reuniao_id);

        if ($reuniao->ataEstaFechada() || $reuniao->estaCancelada()) {
            throw ReuniaoInvalidaException::com('Esta reunião já foi cancelada ou teve a ata fechada: a deliberação não pode mais mudar.');
        }

        if ($reuniao->estaAgendada() && $reuniao->inicio_em->isFuture()) {
            throw ReuniaoInvalidaException::com('O conselho ainda não começou. Registre as deliberações durante ou depois da reunião.');
        }

        if (! array_key_exists($deliberacao, config('reunioes.deliberacoes', []))) {
            throw ReuniaoInvalidaException::com('Escolha uma das deliberações da lista.');
        }

        $pedeEncaminhamento = in_array($deliberacao, config('reunioes.deliberacoes_com_encaminhamento', []), true);
        $descricao = trim((string) ($encaminhamento['descricao'] ?? ''));

        if ($pedeEncaminhamento && $descricao === '') {
            throw ReuniaoInvalidaException::com('Esta deliberação pede um encaminhamento: diga o que será feito.');
        }

        return DB::transaction(function () use ($linha, $reuniao, $deliberacao, $observacao, $por, $pedeEncaminhamento, $descricao, $encaminhamento): ReuniaoAluno {
            $linha->update([
                'deliberacao' => $deliberacao,
                'observacao' => filled($observacao) ? trim((string) $observacao) : null,
                'deliberado_por_user_id' => $por->id,
                'deliberado_em' => now(),
            ]);

            // Trocar a decisão tira da frente o encaminhamento que ela havia gerado, se ninguém o tinha começado a cumprir.
            $linha->encaminhamentos()->abertos()->delete();

            if ($pedeEncaminhamento) {
                $this->reunioes->adicionarEncaminhamento($reuniao, [
                    'descricao' => $descricao,
                    'responsavel_user_id' => $encaminhamento['responsavel_user_id'] ?? null,
                    'prazo' => $encaminhamento['prazo'] ?? now()->addDays((int) config('reunioes.encaminhamento_prazo_dias', 15))->toDateString(),
                    'matricula_id' => $linha->matricula_id,
                    'reuniao_aluno_id' => $linha->id,
                ], $por);
            }

            return $linha->refresh();
        });
    }

    public function pendentesDeDeliberacao(Reuniao $reuniao): int
    {
        return ReuniaoAluno::query()->where('reuniao_id', $reuniao->id)->whereNull('deliberacao')->count();
    }

    /**
     * Quem costuma estar no conselho de uma turma: o professor conselheiro (que preside), quem dá aula para ela e a
     * coordenação do curso. Só entram usuários ativos com acesso.
     *
     * @return Collection<int, User>
     */
    public function sugerirParticipantes(Turma $turma): Collection
    {
        $turma->loadMissing('serie');

        $pessoas = CronogramaAula::query()->where('turma_id', $turma->id)->whereNotNull('pessoa_id')->pluck('pessoa_id');

        if ($turma->professor_conselheiro_id) {
            $pessoas->push($turma->professor_conselheiro_id);
        }

        if ($turma->serie?->curso_id) {
            $pessoas = $pessoas->merge(Coordenador::query()->where('curso_id', $turma->serie->curso_id)->pluck('pessoa_id'));
        }

        $pessoas = $pessoas->unique()->values();

        if ($pessoas->isEmpty()) {
            return collect();
        }

        return User::query()
            ->ativos()
            ->whereHas('pessoas', fn ($q) => $q->whereKey($pessoas->all()))
            ->orderBy('name')
            ->get();
    }

    /**
     * O usuário do professor conselheiro da turma, que preside o conselho.
     */
    public function presidente(Turma $turma): ?User
    {
        if (! $turma->professor_conselheiro_id) {
            return null;
        }

        return User::query()
            ->ativos()
            ->whereHas('pessoas', fn ($q) => $q->whereKey($turma->professor_conselheiro_id))
            ->first();
    }
}

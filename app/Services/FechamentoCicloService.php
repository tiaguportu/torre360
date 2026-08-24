<?php

namespace App\Services;

use App\Enums\SituacaoFinal;
use App\Models\Avaliacao;
use App\Models\Disciplina;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\SituacaoFinalDisciplina;
use Illuminate\Support\Collection;

class FechamentoCicloService
{
    public function __construct(private BoletimService $boletimService) {}

    /**
     * Calcula (sem persistir) a situação final de uma matrícula em uma disciplina, dentro de um período letivo.
     *
     * A média final é a média simples das médias de cada etapa avaliativa. Se houver avaliação(ões)
     * de categoria marcada como "recuperação", o valor consolidado da recuperação substitui a menor
     * média de etapa, desde que seja melhor que ela.
     *
     * @return array{media_final: float|null, situacao: ?SituacaoFinal, medias_etapas: array<int, array{etapa_id: int, etapa_nome: string, media: float|null}>, recuperacao: array{aplicada: bool, valor: float|null, etapa_substituida_id: int|null}}
     */
    public function calcularSituacaoFinal(Matricula $matricula, Disciplina $disciplina, PeriodoLetivo $periodoLetivo): array
    {
        $avaliacoes = Avaliacao::query()
            ->where('turma_id', $matricula->turma_id)
            ->where('disciplina_id', $disciplina->id)
            ->whereHas('etapaAvaliativa', fn ($q) => $q->where('periodo_letivo_id', $periodoLetivo->id))
            ->with(['categoria', 'etapaAvaliativa'])
            ->get();

        $notasAluno = $matricula->notas()->whereNotNull('valor')->get()->keyBy('avaliacao_id');

        $avaliacoesNormais = $avaliacoes->reject(fn (Avaliacao $av) => (bool) $av->categoria?->eh_recuperacao);
        $avaliacoesRecuperacao = $avaliacoes->filter(fn (Avaliacao $av) => (bool) $av->categoria?->eh_recuperacao);

        $etapas = $avaliacoesNormais->pluck('etapaAvaliativa')->filter()->unique('id')->sortBy('id')->values();

        $mediasEtapas = [];
        foreach ($etapas as $etapa) {
            $avaliacoesEtapa = $avaliacoesNormais->where('etapa_avaliativa_id', $etapa->id);

            $mediasEtapas[] = [
                'etapa_id' => $etapa->id,
                'etapa_nome' => $etapa->nome,
                'media' => $this->boletimService->calcularMediaFinal($disciplina->id, $avaliacoesEtapa, $notasAluno),
            ];
        }

        [$mediasEtapas, $recuperacao] = $this->aplicarRecuperacao($mediasEtapas, $avaliacoesRecuperacao, $disciplina, $notasAluno);

        $mediasValidas = collect($mediasEtapas)->pluck('media')->filter(fn ($v) => $v !== null);

        $mediaFinal = $mediasValidas->isNotEmpty() ? round((float) $mediasValidas->avg(), 2) : null;

        return [
            'media_final' => $mediaFinal,
            'situacao' => $this->classificarSituacao($mediaFinal, $periodoLetivo),
            'medias_etapas' => $mediasEtapas,
            'recuperacao' => $recuperacao,
        ];
    }

    /**
     * @param  array<int, array{etapa_id: int, etapa_nome: string, media: float|null}>  $mediasEtapas
     * @return array{0: array<int, array{etapa_id: int, etapa_nome: string, media: float|null}>, 1: array{aplicada: bool, valor: float|null, etapa_substituida_id: int|null}}
     */
    private function aplicarRecuperacao(array $mediasEtapas, Collection $avaliacoesRecuperacao, Disciplina $disciplina, Collection $notasAluno): array
    {
        $recuperacao = ['aplicada' => false, 'valor' => null, 'etapa_substituida_id' => null];

        if ($avaliacoesRecuperacao->isEmpty()) {
            return [$mediasEtapas, $recuperacao];
        }

        $categoriaRecuperacaoId = $avaliacoesRecuperacao->first()->categoria_avaliacao_id;
        $recuperacao['valor'] = $this->boletimService->getMediaConsolidadaCategoria(
            $categoriaRecuperacaoId,
            $disciplina->id,
            $avaliacoesRecuperacao,
            $notasAluno
        );

        $mediasValidas = collect($mediasEtapas)->filter(fn ($m) => $m['media'] !== null);

        if ($recuperacao['valor'] === null || $mediasValidas->isEmpty()) {
            return [$mediasEtapas, $recuperacao];
        }

        $menor = $mediasValidas->sortBy('media')->first();

        if ($recuperacao['valor'] <= $menor['media']) {
            return [$mediasEtapas, $recuperacao];
        }

        foreach ($mediasEtapas as &$item) {
            if ($item['etapa_id'] === $menor['etapa_id']) {
                $item['media'] = $recuperacao['valor'];
            }
        }
        unset($item);

        $recuperacao['aplicada'] = true;
        $recuperacao['etapa_substituida_id'] = $menor['etapa_id'];

        return [$mediasEtapas, $recuperacao];
    }

    public function classificarSituacao(?float $mediaFinal, PeriodoLetivo $periodoLetivo): ?SituacaoFinal
    {
        if ($mediaFinal === null) {
            return null;
        }

        $notaAprovacao = (float) ($periodoLetivo->nota_aprovacao ?? 7.0);
        $notaRecuperacaoMinima = (float) ($periodoLetivo->nota_recuperacao_minima ?? 5.0);

        return match (true) {
            $mediaFinal >= $notaAprovacao => SituacaoFinal::APROVADO,
            $mediaFinal >= $notaRecuperacaoMinima => SituacaoFinal::RECUPERACAO,
            default => SituacaoFinal::REPROVADO,
        };
    }

    /**
     * Fecha o ciclo letivo: calcula e persiste a situação final de todas as disciplinas das matrículas
     * ativas/concluídas do período informado (opcionalmente restrito a uma única turma).
     *
     * O escopo é definido a partir de Matricula.periodo_letivo_id (não de Turma.periodo_letivo_id,
     * que nem sempre está preenchido) — mesma fonte de verdade já usada pelo restante do módulo acadêmico.
     *
     * @return Collection<int, SituacaoFinalDisciplina>
     */
    public function fecharPeriodoLetivo(PeriodoLetivo $periodoLetivo, ?int $turmaId = null): Collection
    {
        $matriculas = Matricula::query()
            ->where('periodo_letivo_id', $periodoLetivo->id)
            ->whereIn('situacao', ['ativa', 'concluido'])
            ->whereHas('turma', fn ($q) => $q->whereIn('tipo_avaliacao', ['notas', 'hibrido']))
            ->when($turmaId, fn ($q) => $q->where('turma_id', $turmaId))
            ->with(['pessoa', 'turma.disciplinas'])
            ->get();

        $resultados = collect();

        foreach ($matriculas as $matricula) {
            foreach ($matricula->turma->disciplinas as $disciplina) {
                $calculo = $this->calcularSituacaoFinal($matricula, $disciplina, $periodoLetivo);

                $registro = SituacaoFinalDisciplina::updateOrCreate(
                    [
                        'matricula_id' => $matricula->id,
                        'disciplina_id' => $disciplina->id,
                        'periodo_letivo_id' => $periodoLetivo->id,
                    ],
                    [
                        'media_final' => $calculo['media_final'],
                        'situacao' => $calculo['situacao'],
                        'calculado_em' => now(),
                    ]
                );

                $registro->setRelation('matricula', $matricula);
                $registro->setRelation('disciplina', $disciplina);

                $resultados->push($registro);
            }
        }

        return $resultados;
    }
}

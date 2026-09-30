<?php

namespace App\Services;

use App\Enums\SituacaoFinal;
use App\Models\Avaliacao;
use App\Models\Disciplina;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\SituacaoFinalDisciplina;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class FechamentoCicloService
{
    public function __construct(private BoletimService $boletimService) {}

    /**
     * Calcula (sem persistir) a situação final de uma matrícula em uma disciplina, dentro de um período letivo.
     *
     * A média final é a média simples das médias de cada etapa avaliativa. Se houver avaliação(ões)
     * de categoria marcada como "recuperação", o comportamento depende de `PeriodoLetivo.recuperacao_por_etapa`:
     * - false (padrão, "recuperação anual"): todas as avaliações de recuperação do período são agrupadas em
     *   um único valor consolidado, que substitui a MENOR média de etapa, desde que seja melhor que ela.
     * - true ("recuperação por etapa"): cada avaliação de recuperação é associada à etapa em que foi lançada
     *   (`etapa_avaliativa_id`) e só pode substituir a média DAQUELA MESMA etapa — permitindo recuperar mais
     *   de uma etapa de forma independente, logo após cada uma fechar.
     *
     * @return array{media_final: float|null, situacao: ?SituacaoFinal, medias_etapas: array<int, array{etapa_id: int, etapa_nome: string, media: float|null}>, recuperacao: array{aplicada: bool, valor: float|null, etapa_substituida_id: int|null, por_etapa?: array<int, array{etapa_id: int, aplicada: bool, valor: float, media_original: float|null}>}}
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

        [$mediasEtapas, $recuperacao] = $this->aplicarRecuperacao($mediasEtapas, $avaliacoesRecuperacao, $disciplina, $notasAluno, $periodoLetivo);

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
    private function aplicarRecuperacao(array $mediasEtapas, Collection $avaliacoesRecuperacao, Disciplina $disciplina, Collection $notasAluno, PeriodoLetivo $periodoLetivo): array
    {
        $recuperacao = ['aplicada' => false, 'valor' => null, 'etapa_substituida_id' => null];

        if ($avaliacoesRecuperacao->isEmpty()) {
            return [$mediasEtapas, $recuperacao];
        }

        if ($periodoLetivo->recuperacao_por_etapa) {
            return $this->aplicarRecuperacaoPorEtapa($mediasEtapas, $avaliacoesRecuperacao, $disciplina, $notasAluno);
        }

        return $this->aplicarRecuperacaoAnual($mediasEtapas, $avaliacoesRecuperacao, $disciplina, $notasAluno);
    }

    /**
     * Comportamento pré-existente ("recuperação anual"): agrupa todas as avaliações de recuperação do
     * período num único valor consolidado, que substitui a MENOR média de etapa, se for melhor que ela.
     *
     * @param  array<int, array{etapa_id: int, etapa_nome: string, media: float|null}>  $mediasEtapas
     * @return array{0: array<int, array{etapa_id: int, etapa_nome: string, media: float|null}>, 1: array{aplicada: bool, valor: float|null, etapa_substituida_id: int|null}}
     */
    private function aplicarRecuperacaoAnual(array $mediasEtapas, Collection $avaliacoesRecuperacao, Disciplina $disciplina, Collection $notasAluno): array
    {
        $recuperacao = ['aplicada' => false, 'valor' => null, 'etapa_substituida_id' => null];

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

    /**
     * Modo "recuperação por etapa": cada etapa é recuperada de forma independente, com a(s) sua(s)
     * própria(s) avaliação(ões) de recuperação (identificadas por `etapa_avaliativa_id`). Uma etapa só
     * é substituída se a recuperação daquela mesma etapa for melhor que a média original dela (ou se a
     * etapa não tinha nenhuma nota lançada).
     *
     * @param  array<int, array{etapa_id: int, etapa_nome: string, media: float|null}>  $mediasEtapas
     * @return array{0: array<int, array{etapa_id: int, etapa_nome: string, media: float|null}>, 1: array{aplicada: bool, valor: float|null, etapa_substituida_id: int|null, por_etapa: array<int, array{etapa_id: int, aplicada: bool, valor: float, media_original: float|null}>}}
     */
    private function aplicarRecuperacaoPorEtapa(array $mediasEtapas, Collection $avaliacoesRecuperacao, Disciplina $disciplina, Collection $notasAluno): array
    {
        $recuperacao = ['aplicada' => false, 'valor' => null, 'etapa_substituida_id' => null, 'por_etapa' => []];

        $categoriaRecuperacaoId = $avaliacoesRecuperacao->first()->categoria_avaliacao_id;
        $avaliacoesPorEtapa = $avaliacoesRecuperacao->groupBy('etapa_avaliativa_id');

        $primeiraSubstituicao = null;

        foreach ($mediasEtapas as &$item) {
            $avaliacoesRecuperacaoEtapa = $avaliacoesPorEtapa->get($item['etapa_id']);

            if (! $avaliacoesRecuperacaoEtapa || $avaliacoesRecuperacaoEtapa->isEmpty()) {
                continue;
            }

            $valorRecuperacaoEtapa = $this->boletimService->getMediaConsolidadaCategoria(
                $categoriaRecuperacaoId,
                $disciplina->id,
                $avaliacoesRecuperacaoEtapa,
                $notasAluno
            );

            if ($valorRecuperacaoEtapa === null) {
                continue;
            }

            $aplicadaNestaEtapa = $item['media'] === null || $valorRecuperacaoEtapa > $item['media'];

            $recuperacao['por_etapa'][] = [
                'etapa_id' => $item['etapa_id'],
                'aplicada' => $aplicadaNestaEtapa,
                'valor' => $valorRecuperacaoEtapa,
                'media_original' => $item['media'],
            ];

            if ($aplicadaNestaEtapa) {
                $item['media'] = $valorRecuperacaoEtapa;
                $recuperacao['aplicada'] = true;

                if ($primeiraSubstituicao === null) {
                    $primeiraSubstituicao = ['valor' => $valorRecuperacaoEtapa, 'etapa_substituida_id' => $item['etapa_id']];
                }
            }
        }
        unset($item);

        if ($primeiraSubstituicao !== null) {
            $recuperacao['valor'] = $primeiraSubstituicao['valor'];
            $recuperacao['etapa_substituida_id'] = $primeiraSubstituicao['etapa_substituida_id'];
        }

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
     * Se a disciplina deixou de estar em situação de recuperação em relação ao cálculo anterior, os
     * dados de um eventual exame final já lançado são limpos (não fazem mais sentido para a nova
     * situação); se a disciplina continua em recuperação, um exame final já lançado é preservado.
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

                $chave = [
                    'matricula_id' => $matricula->id,
                    'disciplina_id' => $disciplina->id,
                    'periodo_letivo_id' => $periodoLetivo->id,
                ];

                $dadosAtualizacao = [
                    'media_final' => $calculo['media_final'],
                    'situacao' => $calculo['situacao'],
                    'calculado_em' => now(),
                ];

                $existente = SituacaoFinalDisciplina::where($chave)->first();

                if ($existente && $existente->situacao === SituacaoFinal::RECUPERACAO && $calculo['situacao'] !== SituacaoFinal::RECUPERACAO) {
                    $dadosAtualizacao['nota_exame_final'] = null;
                    $dadosAtualizacao['media_final_pos_exame'] = null;
                    $dadosAtualizacao['situacao_final_pos_exame'] = null;
                }

                $registro = SituacaoFinalDisciplina::updateOrCreate($chave, $dadosAtualizacao);

                $registro->setRelation('matricula', $matricula);
                $registro->setRelation('disciplina', $disciplina);
                $registro->setRelation('periodoLetivo', $periodoLetivo);

                $resultados->push($registro);
            }
        }

        return $resultados;
    }

    /**
     * Indica se a disciplina está apta a receber o lançamento de exame final: o período letivo precisa
     * ter o exame final habilitado, a situação precisa estar em recuperação, e o exame ainda não pode
     * ter sido lançado.
     */
    public function elegivelExameFinal(SituacaoFinalDisciplina $registro): bool
    {
        $periodoLetivo = $registro->relationLoaded('periodoLetivo')
            ? $registro->periodoLetivo
            : PeriodoLetivo::find($registro->periodo_letivo_id);

        return (bool) $periodoLetivo?->exame_final_habilitado
            && $registro->situacao === SituacaoFinal::RECUPERACAO
            && $registro->situacao_final_pos_exame === null;
    }

    /**
     * Registra a nota do exame final de uma disciplina em recuperação e calcula a situação definitiva.
     *
     * Fórmula (fixa nesta primeira versão): média simples entre a média final do período e a nota do
     * exame. O resultado é comparado a `PeriodoLetivo.nota_aprovacao_pos_exame` — só existem os dois
     * desfechos possíveis após o exame final: Aprovado ou Reprovado (não há uma segunda recuperação).
     */
    public function registrarExameFinal(SituacaoFinalDisciplina $registro, float $notaExameFinal): SituacaoFinalDisciplina
    {
        $periodoLetivo = $registro->relationLoaded('periodoLetivo')
            ? $registro->periodoLetivo
            : PeriodoLetivo::findOrFail($registro->periodo_letivo_id);

        if (! $periodoLetivo->exame_final_habilitado) {
            throw new InvalidArgumentException('Este período letivo não tem o exame final habilitado.');
        }

        if ($registro->situacao !== SituacaoFinal::RECUPERACAO) {
            throw new InvalidArgumentException('Só é possível lançar exame final para disciplinas em situação de recuperação.');
        }

        $mediaFinalPosExame = round(((float) $registro->media_final + $notaExameFinal) / 2, 2);
        $notaAprovacaoPosExame = (float) ($periodoLetivo->nota_aprovacao_pos_exame ?? 5.0);

        $registro->nota_exame_final = $notaExameFinal;
        $registro->media_final_pos_exame = $mediaFinalPosExame;
        $registro->situacao_final_pos_exame = $mediaFinalPosExame >= $notaAprovacaoPosExame
            ? SituacaoFinal::APROVADO
            : SituacaoFinal::REPROVADO;
        $registro->save();

        return $registro;
    }
}

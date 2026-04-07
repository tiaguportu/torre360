<?php

namespace App\Filament\Schemas\Components;

use App\Models\Avaliacao;
use App\Models\Matricula;
use App\Models\Nota;
use App\Models\EtapaAvaliativa;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Collection;

class BoletimeGradesTable extends Component
{
    protected string $view = 'filament.schemas.components.boletime-grades-table';

    public static function make(): static
    {
        return app(static::class);
    }

    public function getMatricula(): ?Matricula
    {
        $record = $this->getRecord();
        if ($record instanceof Matricula) {
            return $record;
        }
        return $record?->matriculas?->first();
    }

    /**
     * Retorna apenas as etapas que possuem ao menos uma nota lançada para o aluno.
     */
    public function getEtapasComNotas(): Collection
    {
        $matricula = $this->getMatricula();
        if (!$matricula) return collect();

        // Pega os IDs das etapas vinculadas às notas do aluno
        $etapaIds = Avaliacao::query()
            ->whereHas('notas', fn($q) => $q->where('matricula_id', $matricula->id)->whereNotNull('valor'))
            ->where('turma_id', $matricula->turma_id)
            ->pluck('etapa_avaliativa_id')
            ->unique();

        return EtapaAvaliativa::whereIn('id', $etapaIds)->orderBy('id')->get();
    }

    /**
     * Retorna avaliações da etapa e turma, agrupadas por disciplina.
     */
    public function getAvaliacoesPorEtapa(int $etapaId): Collection
    {
        $matricula = $this->getMatricula();
        if (!$matricula) return collect();

        return Avaliacao::query()
            ->where('turma_id', $matricula->turma_id)
            ->where('etapa_avaliativa_id', $etapaId)
            ->with(['categoria', 'etapaAvaliativa', 'disciplina'])
            ->get()
            ->groupBy('disciplina_id');
    }

    public function getNotasAluno(): Collection
    {
        $matricula = $this->getMatricula();
        if (!$matricula) return collect();

        return $matricula->notas()
            ->whereNotNull('valor')
            ->get()
            ->keyBy('avaliacao_id');
    }

    public function getNotasTurmaAgrupadas(int $turmaId): Collection
    {
        return Nota::query()
            ->whereHas('matricula', fn ($q) => $q->where('turma_id', $turmaId))
            ->whereNotNull('valor')
            ->get()
            ->groupBy('avaliacao_id');
    }

    /**
     * Calcula a média final da disciplina considerando agregação por categoria e substituições.
     */
    public function calcularMediaFinal(int $disciplinaId, int $etapaId, Collection $avaliacoesEtapa, Collection $notasAluno): ?float
    {
        $avs = $avaliacoesEtapa->get($disciplinaId, collect());
        if ($avs->isEmpty()) return null;

        // Categorias únicas desta etapa/disciplina
        $categorias = $avs->map(fn($av) => $av->categoria)->filter()->unique('id');

        $somasCategorias = [];
        foreach ($categorias as $cat) {
            $valor = $this->getMediaConsolidadaCategoria($cat->id, $disciplinaId, $etapaId, $avaliacoesEtapa, $notasAluno);
            if ($valor !== null) {
                $somasCategorias[$cat->id] = [
                    'valor' => $valor,
                    'substitui_id' => $cat->categoria_avaliacao_substituicao_id,
                    'ignorar' => false
                ];
            }
        }

        foreach ($somasCategorias as $id => &$item) {
            if ($item['substitui_id'] && isset($somasCategorias[$item['substitui_id']])) {
                if ($item['valor'] > $somasCategorias[$item['substitui_id']]['valor']) {
                    $somasCategorias[$item['substitui_id']]['ignorar'] = true;
                } else {
                    $item['ignorar'] = true;
                }
            }
        }

        $validas = array_filter($somasCategorias, fn($i) => !$i['ignorar']);
        if (empty($validas)) return null;

        return array_sum(array_column($validas, 'valor')) / count($validas);
    }

    public function getMediaConsolidadaCategoria(int $categoriaId, int $disciplinaId, int $etapaId, Collection $avaliacoesEtapa, Collection $notasAluno): ?float
    {
        $avs = $avaliacoesEtapa->get($disciplinaId, collect())->where('categoria_avaliacao_id', $categoriaId);
        if ($avs->isEmpty()) return null;
        
        $soma = 0; $count = 0;
        foreach ($avs as $av) {
            $nota = $notasAluno->get($av->id);
            if ($nota) { $soma += (float) $nota->valor; $count++; }
        }
        return $count > 0 ? $soma / $count : null;
    }

    public function isCategoriaIgnorada(int $categoriaId, int $disciplinaId, int $etapaId, Collection $avaliacoesEtapa, Collection $notasAluno): bool
    {
        $avs = $avaliacoesEtapa->get($disciplinaId, collect());
        $categorias = $avs->map(fn($av) => $av->categoria)->filter()->unique('id');

        $dados = [];
        foreach ($categorias as $cat) {
            $dados[$cat->id] = [
                'valor' => $this->getMediaConsolidadaCategoria($cat->id, $disciplinaId, $etapaId, $avaliacoesEtapa, $notasAluno),
                'substitui_id' => $cat->categoria_avaliacao_substituicao_id,
            ];
        }

        foreach ($dados as $id => $item) {
            if ($id == $categoriaId && $item['valor'] !== null) {
                // Checar se existe alguém que me substitui e é melhor
                $substituto = $categorias->first(fn($c) => $c->categoria_avaliacao_substituicao_id == $id);
                if ($substituto) {
                    $vSub = $this->getMediaConsolidadaCategoria($substituto->id, $disciplinaId, $etapaId, $avaliacoesEtapa, $notasAluno);
                    if ($vSub !== null && $vSub > $item['valor']) return true;
                }
                
                // Checar se EU sou o substituto mas sou pior que o original
                if ($item['substitui_id'] && isset($dados[$item['substitui_id']])) {
                    $vOrig = $dados[$item['substitui_id']]['valor'];
                    if ($vOrig !== null && $item['valor'] <= $vOrig) return true;
                }
            }
        }
        return false;
    }

    /**
     * Calcula a média da turma para a etapa/disciplina.
     */
    public function getMediaTurmaEtapa(int $disciplinaId, int $etapaId, Collection $avaliacoesEtapa, Collection $notasTurma): ?float
    {
        $avs = $avaliacoesEtapa->get($disciplinaId, collect());
        $matriculaIds = [];
        foreach ($avs as $av) {
            foreach ($notasTurma->get($av->id, collect()) as $n) {
                $matriculaIds[$n->matricula_id] = true;
            }
        }

        if (empty($matriculaIds)) return null;

        $somaMediasAlunos = 0; $countAlunos = 0;
        foreach (array_keys($matriculaIds) as $mId) {
            $notasDoAluno = collect();
            foreach ($avs as $av) {
                $nota = $notasTurma->get($av->id, collect())->firstWhere('matricula_id', $mId);
                if ($nota) $notasDoAluno->put($av->id, $nota);
            }
            $media = $this->calcularMediaFinal($disciplinaId, $etapaId, $avaliacoesEtapa, $notasDoAluno);
            if ($media !== null) { $somaMediasAlunos += $media; $countAlunos++; }
        }
        return $countAlunos > 0 ? $somaMediasAlunos / $countAlunos : null;
    }
}

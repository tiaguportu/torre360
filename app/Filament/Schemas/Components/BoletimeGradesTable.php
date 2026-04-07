<?php

namespace App\Filament\Schemas\Components;

use App\Models\Avaliacao;
use App\Models\Matricula;
use App\Models\Nota;
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
     * Retorna todas as avaliações da turma, agrupadas por disciplina.
     *
     * @return Collection — keyed by disciplina_id, each value is Collection of Avaliacao
     */
    public function getAvaliacoesPorDisciplina(): Collection
    {
        $matricula = $this->getMatricula();
        $turma = $matricula?->turma;

        if (! $turma) {
            return collect();
        }

        return $turma->avaliacoes()
            ->with(['disciplina', 'etapaAvaliativa', 'categoria'])
            ->orderBy('disciplina_id')
            ->orderBy('etapa_avaliativa_id')
            ->get()
            ->groupBy('disciplina_id');
    }

    /**
     * Retorna as notas do aluno (matricula atual), indexadas por avaliacao_id.
     *
     * @return Collection<int, Nota>
     */
    public function getNotasAluno(): Collection
    {
        $matricula = $this->getMatricula();

        if (! $matricula) {
            return collect();
        }

        return $matricula->notas()
            ->with('avaliacao')
            ->get()
            ->keyBy('avaliacao_id');
    }

    /**
     * Retorna as notas de todos os alunos da turma, agrupadas por avaliacao_id.
     * Usado para calcular a média da turma.
     *
     * @return Collection<int, Collection<Nota>>
     */
    public function getNotasTurmaAgrupadas(): Collection
    {
        $matricula = $this->getMatricula();
        $turma = $matricula?->turma;

        if (! $turma) {
            return collect();
        }

        return Nota::query()
            ->whereHas('matricula', fn ($q) => $q->where('turma_id', $turma->id))
            ->whereNotNull('valor')
            ->get()
            ->groupBy('avaliacao_id');
    }

    /**
     * Lógica central de cálculo de média com suporte a substituição.
     * Recebe as avaliações de uma disciplina e as notas (pode ser de um aluno ou valores brutos).
     */
    private function calcularMediaComSubstituicao(Collection $avaliacoes, Collection $notasDoAluno): ?float
    {
        $dados = [];

        // 1. Organizar dados base das avaliações e notas disponíveis
        foreach ($avaliacoes as $avaliacao) {
            $nota = $notasDoAluno->get($avaliacao->id);

            // Nota pode vir como objeto Nota ou apenas o valor (no caso da média da turma)
            $valor = null;
            if ($nota) {
                $valor = (float) ($nota instanceof Nota ? $nota->valor : (is_array($nota) ? ($nota['valor'] ?? null) : $nota));
            }

            $dados[$avaliacao->id] = [
                'valor' => $valor,
                'peso' => (float) ($avaliacao->nota_maxima ?? 10),
                'categoria_id' => $avaliacao->categoria_id,
                'substitui_id' => $avaliacao->categoria?->categoria_avaliacao_substituicao_id,
                'ignorar' => false,
            ];
        }

        // 2. Aplicar lógica de substituição
        // Se B substitui A, comparamos as notas de B e A dentro desta disciplina.
        foreach ($dados as $id => &$item) {
            if ($item['substitui_id'] && $item['valor'] !== null) {
                foreach ($dados as $outroId => &$outroItem) {
                    if ($outroId !== $id && $outroItem['categoria_id'] == $item['substitui_id'] && $outroItem['valor'] !== null) {
                        // Se a nota substituta é maior, ignora a original. Caso contrário, ignora a substituta.
                        if ($item['valor'] > $outroItem['valor']) {
                            $outroItem['ignorar'] = true;
                        } else {
                            $item['ignorar'] = true;
                        }
                    }
                }
            }
        }

        // 3. Calcular média ponderada dos itens não ignorados
        $totalPeso = 0;
        $totalPonderado = 0;
        $temNota = false;

        foreach ($dados as $item) {
            if (! $item['ignorar'] && $item['valor'] !== null) {
                $totalPonderado += $item['valor'] * $item['peso'];
                $totalPeso += $item['peso'];
                $temNota = true;
            }
        }

        if (! $temNota || $totalPeso === 0) {
            return null;
        }

        return round($totalPonderado / $totalPeso, 2);
    }

    /**
     * Calcula a média do aluno para uma disciplina específica.
     */
    public function getMediaAlunoPorDisciplina(int $disciplinaId, Collection $notasAluno, Collection $avaliacoesPorDisciplina): ?float
    {
        $avaliacoes = $avaliacoesPorDisciplina->get($disciplinaId, collect());

        return $this->calcularMediaComSubstituicao($avaliacoes, $notasAluno);
    }

    /**
     * Calcula a média da turma para uma disciplina específica.
     */
    public function getMediaTurmaPorDisciplina(int $disciplinaId, Collection $notasTurma, Collection $avaliacoesPorDisciplina): ?float
    {
        $avaliacoes = $avaliacoesPorDisciplina->get($disciplinaId, collect());

        $somaMediasAlunos = 0;
        $countAlunos = 0;
        $matriculaIds = [];

        // Identifica todos os alunos que têm notas nesta disciplina
        foreach ($avaliacoes as $avaliacao) {
            foreach ($notasTurma->get($avaliacao->id, collect()) as $nota) {
                $matriculaIds[$nota->matricula_id] = true;
            }
        }

        if (empty($matriculaIds)) {
            return null;
        }

        // Para cada aluno, calcula sua média individual (com substituições) e soma para a média da turma
        foreach (array_keys($matriculaIds) as $matriculaId) {
            $notasDoAluno = collect();
            foreach ($avaliacoes as $avaliacao) {
                $nota = $notasTurma->get($avaliacao->id, collect())->firstWhere('matricula_id', $matriculaId);
                if ($nota) {
                    $notasDoAluno->put($avaliacao->id, $nota->valor);
                }
            }

            $mediaIndividual = $this->calcularMediaComSubstituicao($avaliacoes, $notasDoAluno);

            if ($mediaIndividual !== null) {
                $somaMediasAlunos += $mediaIndividual;
                $countAlunos++;
            }
        }

        if ($countAlunos === 0) {
            return null;
        }

        return round($somaMediasAlunos / $countAlunos, 2);
    }

    /**
     * Calcula a média geral do aluno (média das médias por disciplina).
     */
    public function getMediaGeralAluno(Collection $avaliacoesPorDisciplina, Collection $notasAluno): ?float
    {
        $medias = $avaliacoesPorDisciplina->keys()->map(
            fn ($disciplinaId) => $this->getMediaAlunoPorDisciplina($disciplinaId, $notasAluno, $avaliacoesPorDisciplina)
        )->filter(fn ($m) => $m !== null);

        if ($medias->isEmpty()) {
            return null;
        }

        return round($medias->avg(), 2);
    }

    /**
     * Calcula a média geral da turma (média das médias por disciplina).
     */
    public function getMediaGeralTurma(Collection $avaliacoesPorDisciplina, Collection $notasTurma): ?float
    {
        $medias = $avaliacoesPorDisciplina->keys()->map(
            fn ($disciplinaId) => $this->getMediaTurmaPorDisciplina($disciplinaId, $notasTurma, $avaliacoesPorDisciplina)
        )->filter(fn ($m) => $m !== null);

        if ($medias->isEmpty()) {
            return null;
        }

        return round($medias->avg(), 2);
    }

    public function getViewData(): array
    {
        $matricula = $this->getMatricula();
        $avaliacoesPorDisciplina = $this->getAvaliacoesPorDisciplina();
        $disciplinas = $avaliacoesPorDisciplina
            ->map(fn ($avs) => $avs->first()->disciplina)
            ->filter()
            ->sortBy('nome')
            ->values();
        $notasAluno = $this->getNotasAluno();
        $notasTurma = $this->getNotasTurmaAgrupadas();

        return compact('matricula', 'avaliacoesPorDisciplina', 'disciplinas', 'notasAluno', 'notasTurma');
    }

    /**
     * Método auxiliar para a view saber se uma nota foi ignorada por outra na disciplina.
     */
    public function isNotaIgnorada(int $avaliacaoId, int $disciplinaId, Collection $notasAluno, Collection $avaliacoesPorDisciplina): bool
    {
        $avaliacoes = $avaliacoesPorDisciplina->get($disciplinaId, collect());
        $dados = [];

        foreach ($avaliacoes as $av) {
            $nota = $notasAluno->get($av->id);
            $dados[$av->id] = [
                'valor' => $nota ? (float) $nota->valor : null,
                'categoria_id' => $av->categoria_id,
                'substitui_id' => $av->categoria?->categoria_avaliacao_substituicao_id,
                'ignorar' => false,
            ];
        }

        foreach ($dados as $id => &$item) {
            if ($item['substitui_id'] && $item['valor'] !== null) {
                foreach ($dados as $outroId => &$outroItem) {
                    if ($outroId !== $id && $outroItem['categoria_id'] == $item['substitui_id'] && $outroItem['valor'] !== null) {
                        if ($item['valor'] > $outroItem['valor']) {
                            $outroItem['ignorar'] = true;
                        } else {
                            $item['ignorar'] = true;
                        }
                    }
                }
            }
        }

        return $dados[$avaliacaoId]['ignorar'] ?? false;
    }
}

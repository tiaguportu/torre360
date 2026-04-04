<?php

namespace App\Filament\Schemas\Components;

use App\Models\CategoriaAvaliacao;
use App\Models\EtapaAvaliativa;
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

    public function getEtapas(): Collection
    {
        return EtapaAvaliativa::orderBy('id')->get();
    }

    public function getCategorias(): Collection
    {
        return CategoriaAvaliacao::orderBy('id')->get();
    }

    public function getMatricula()
    {
        $record = $this->getRecord();

        if ($record instanceof Matricula) {
            return $record;
        }

        return $record?->matriculas?->first();
    }

    public function getNotas(): Collection
    {
        $matricula = $this->getMatricula();

        if (! $matricula) {
            return collect();
        }

        return $matricula->notas()
            ->with(['avaliacao.disciplina', 'avaliacao.categoria', 'avaliacao.etapaAvaliativa'])
            ->get();
    }

    public function getDisciplinas(Collection $notas): Collection
    {
        return $notas->pluck('avaliacao.disciplina')
            ->filter()
            ->unique('id')
            ->sortBy('nome');
    }

    public function getNotasAgrupadasTurma(): Collection
    {
        $matricula = $this->getMatricula();
        $turma = $matricula?->turma;

        if (! $turma) {
            return collect();
        }

        return Nota::query()
            ->whereHas('matricula', fn ($q) => $q->where('turma_id', $turma->id))
            ->with('avaliacao')
            ->get()
            ->groupBy(['avaliacao.etapa_avaliativa_id', 'avaliacao.disciplina_id']);
    }

    public function getFaltasAgrupadas(): Collection
    {
        $matricula = $this->getMatricula();

        if (! $matricula) {
            return collect();
        }

        return $matricula->frequenciaEscolars()
            ->where('presente', false)
            ->get()
            ->groupBy('disciplina_id');
    }

    public function getTotalAulasAgrupadas(): Collection
    {
        $matricula = $this->getMatricula();

        if (! $matricula) {
            return collect();
        }

        return $matricula->frequenciaEscolars()
            ->get()
            ->groupBy('disciplina_id');
    }
}

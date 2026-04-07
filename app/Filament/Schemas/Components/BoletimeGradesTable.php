<?php

namespace App\Filament\Schemas\Components;

use App\Models\Avaliacao;
use App\Models\Matricula;
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
}

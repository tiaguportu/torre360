<?php

namespace App\Filament\Schemas\Components;

use App\Models\Avaliacao;
use App\Models\EtapaAvaliativa;
use App\Models\Matricula;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Collection;

class BoletimEdicaoGradesTable extends Component
{
    protected string $view = 'filament.schemas.components.boletim-edicao-grades-table';

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
     * Retorna as etapas para edição. Diferente da visualização, aqui podemos querer mostrar todas as etapas
     * ou apenas as que a escola permitir editar.
     */
    public function getEtapas(): Collection
    {
        $matricula = $this->getMatricula();
        if (! $matricula) {
            return collect();
        }

        // Pega todas as etapas que têm avaliações para a turma da matrícula
        $etapaIds = Avaliacao::query()
            ->where('turma_id', $matricula->turma_id)
            ->pluck('etapa_avaliativa_id')
            ->unique();

        return EtapaAvaliativa::whereIn('id', $etapaIds)->orderBy('id')->get();
    }
}

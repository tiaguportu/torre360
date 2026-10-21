<?php

declare(strict_types=1);

namespace App\Filament\Resources\CenarioReajustes\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\CenarioReajustes\CenarioReajusteResource;
use App\Filament\Resources\CenarioReajustes\Pages\Concerns\TemAcoesDoCenario;
use App\Support\HelpContent;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCenarioReajuste extends ViewRecord
{
    use HasAjudaAction;
    use TemAcoesDoCenario;

    protected static string $resource = CenarioReajusteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...$this->acoesDoCenario(),
            EditAction::make()->visible(fn (): bool => (bool) auth()->user()?->can('update', $this->getRecord())),
            $this->ajudaAction('Cenário de reajuste', HelpContent::make('📈', 'Cenário de reajuste', 'Valores por série, projeção de receita e etapas do reajuste.')
                ->alerta('Aplicado, o preço da série vale para as próximas rematrículas efetivadas. Desfazer só é possível antes da primeira efetivação.')),
        ];
    }
}

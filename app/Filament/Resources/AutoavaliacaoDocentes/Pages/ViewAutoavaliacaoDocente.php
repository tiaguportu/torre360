<?php

declare(strict_types=1);

namespace App\Filament\Resources\AutoavaliacaoDocentes\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\AutoavaliacaoDocentes\AutoavaliacaoDocenteResource;
use App\Support\HelpContent;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAutoavaliacaoDocente extends ViewRecord
{
    use HasAjudaAction;

    protected static string $resource = AutoavaliacaoDocenteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label('Continuar preenchendo')->visible(fn (): bool => (bool) auth()->user()?->can('update', $this->getRecord())),
            $this->ajudaAction('Autoavaliação', HelpContent::make('🪞', 'Autoavaliação', 'Notas que o professor deu a si mesmo no ciclo.')
                ->alerta('Conteúdo confidencial: só o professor, a coordenação e a administração veem a autoavaliação enviada.')),
        ];
    }
}

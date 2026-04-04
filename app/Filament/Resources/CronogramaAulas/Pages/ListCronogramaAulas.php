<?php

namespace App\Filament\Resources\CronogramaAulas\Pages;

use App\Filament\Resources\CronogramaAulas\CronogramaAulaResource;
use App\Models\CronogramaAula;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCronogramaAulas extends ListRecords
{
    protected static string $resource = CronogramaAulaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('calendar')
                ->label('Visualizar Calendário')
                ->icon('heroicon-o-calendar')
                ->url(fn (): string => CronogramaAulaResource::getUrl('calendar')),
            Action::make('verificaConflitos')
                ->label('Verificar Conflitos')
                ->color('danger')
                ->icon('heroicon-o-exclamation-triangle')
                ->url(fn (): string => CronogramaAulaResource::getUrl('verifica-conflitos'))
                ->visible(fn () => auth()->user()->can('verificaConflitos', CronogramaAula::class)),
            CreateAction::make(),
        ];
    }
}

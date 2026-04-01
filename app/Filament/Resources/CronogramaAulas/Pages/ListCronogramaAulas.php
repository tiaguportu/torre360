<?php

namespace App\Filament\Resources\CronogramaAulas\Pages;

use App\Filament\Resources\CronogramaAulas\CronogramaAulaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCronogramaAulas extends ListRecords
{
    protected static string $resource = CronogramaAulaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('verificaConflitos')
                ->label('Verificar Conflitos')
                ->color('danger')
                ->icon('heroicon-o-exclamation-triangle')
                ->url(fn (): string => CronogramaAulaResource::getUrl('verifica-conflitos')),
            CreateAction::make(),
        ];
    }
}

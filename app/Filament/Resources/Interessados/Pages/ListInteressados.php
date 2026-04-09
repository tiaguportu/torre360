<?php

namespace App\Filament\Resources\Interessados\Pages;

use App\Filament\Resources\Interessados\InteressadoResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInteressados extends ListRecords
{
    protected static string $resource = InteressadoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('kanban')
                ->label('Ver Kanban')
                ->icon('heroicon-o-view-columns')
                ->color('info')
                ->url(InteressadoResource::getUrl('kanban')),
            CreateAction::make(),
        ];
    }
}

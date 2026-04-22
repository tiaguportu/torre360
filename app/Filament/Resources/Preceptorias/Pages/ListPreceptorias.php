<?php

namespace App\Filament\Resources\Preceptorias\Pages;

use App\Filament\Resources\Preceptorias\PreceptoriaResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPreceptorias extends ListRecords
{
    protected static string $resource = PreceptoriaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('agendar')
                ->label('Agendar Preceptoria')
                ->color('info')
                ->icon('heroicon-o-calendar-date-range')
                ->url(fn () => $this->getResource()::getUrl('agendar'))
                ->visible(fn () => auth()->user()->can('Agendar:Preceptoria')),

            CreateAction::make(),
        ];
    }
}

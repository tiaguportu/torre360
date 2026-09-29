<?php

namespace App\Filament\Resources\PlanoAulas\Pages;

use App\Filament\Resources\PlanoAulas\PlanoAulaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPlanoAulas extends ListRecords
{
    protected static string $resource = PlanoAulaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\DisponibilidadeVisitas\Pages;

use App\Filament\Resources\DisponibilidadeVisitas\DisponibilidadeVisitaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDisponibilidadeVisitas extends ManageRecords
{
    protected static string $resource = DisponibilidadeVisitaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Novo horário')];
    }
}

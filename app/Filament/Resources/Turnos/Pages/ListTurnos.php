<?php

namespace App\Filament\Resources\Turnos\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\Turnos\TurnoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTurnos extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = TurnoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaCadastro('🌤️', 'Turnos', 'Períodos do dia em que as turmas funcionam.', 'Turno',
                'Veja nome, hora de início e hora de fim.', 'Cadastre um turno com seus horários.', 'Ajuste nome ou horários.'),
        ];
    }
}

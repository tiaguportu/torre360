<?php

namespace App\Filament\Resources\Estados\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\Estados\EstadoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEstados extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = EstadoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaCadastro('🗺️', 'Estados', 'Unidades da federação usadas nos endereços.', 'Estado',
                'Veja nome, sigla e país.', 'Cadastre um estado com sigla e país.', 'Corrija nome, sigla ou país.'),
        ];
    }
}

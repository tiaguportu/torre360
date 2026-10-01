<?php

namespace App\Filament\Resources\Cidades\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\Cidades\CidadeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCidades extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = CidadeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaCadastro('🏙️', 'Cidades', 'Municípios usados nos endereços.', 'Cidade',
                'Veja nome, estado e código IBGE.', 'Cadastre um município com seu estado e código IBGE.', 'Corrija nome, estado ou código IBGE.'),
        ];
    }
}

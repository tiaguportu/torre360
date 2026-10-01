<?php

namespace App\Filament\Resources\CodigoBacens\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\CodigoBacens\CodigoBacenResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCodigoBacens extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = CodigoBacenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaCadastro('🔢', 'Códigos BACEN', 'Relação de instituições financeiras do Banco Central.', 'CodigoBacen',
                'Veja código, ISPB, nome reduzido e nome por extenso.', 'Cadastre uma instituição financeira.', 'Corrija código, ISPB ou nomes.'),
        ];
    }
}

<?php

namespace App\Filament\Resources\Enderecos\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\Enderecos\EnderecoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEnderecos extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = EnderecoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaCadastro('📍', 'Endereços', 'Endereços usados nos cadastros de pessoas e unidades.', 'Endereco',
                'Veja logradouro, número, complemento, bairro, cidade, CEP e tipo.', 'Cadastre um endereço.', 'Corrija os dados do endereço.'),
        ];
    }
}

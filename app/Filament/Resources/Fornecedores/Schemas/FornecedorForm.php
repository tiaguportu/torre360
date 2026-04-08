<?php

namespace App\Filament\Resources\Fornecedores\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class FornecedorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('razao_social')
                    ->label('Razão Social')
                    ->required(),
                TextInput::make('nome_fantasia')
                    ->label('Nome Fantasia'),
                TextInput::make('cnpj')
                    ->label('CNPJ')
                    ->mask('99.999.999/9999-99')
                    ->unique(ignoreRecord: true),
                TextInput::make('email')
                    ->label('E-mail')
                    ->email(),
                TextInput::make('telefone')
                    ->label('Telefone')
                    ->tel(),
            ]);
    }
}

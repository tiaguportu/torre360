<?php

namespace App\Filament\Resources\Enderecos\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class EnderecoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('tipo')
                    ->options([
                        'residencial' => 'Residencial',
                        'comercial' => 'Comercial',
                    ])
                    ->default('residencial')
                    ->required(),
                Select::make('cidade_id')
                    ->relationship('cidade', 'nome')
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->nome}-{$record->estado?->sigla}")
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('logradouro')
                    ->required(),
                TextInput::make('numero'),
                TextInput::make('bairro'),
                TextInput::make('cep'),
            ]);
    }
}

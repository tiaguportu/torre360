<?php

namespace App\Filament\Resources\DocumentoObrigatorios\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DocumentoObrigatorioForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('curso_id')
                    ->relationship('curso', 'nome_externo')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('nome')
                    ->required(),
                Toggle::make('flag_obrigatorio')
                    ->required(),
                Toggle::make('flag_ativo')
                    ->required(),
            ]);
    }
}

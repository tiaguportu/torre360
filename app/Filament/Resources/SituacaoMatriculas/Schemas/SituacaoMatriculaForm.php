<?php

namespace App\Filament\Resources\SituacaoMatriculas\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SituacaoMatriculaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nome')
                    ->required()
                    ->maxLength(255),
            ]);
    }
}

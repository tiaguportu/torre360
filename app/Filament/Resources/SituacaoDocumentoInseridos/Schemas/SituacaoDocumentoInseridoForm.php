<?php

namespace App\Filament\Resources\SituacaoDocumentoInseridos\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SituacaoDocumentoInseridoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nome')
                    ->required(),
            ]);
    }
}

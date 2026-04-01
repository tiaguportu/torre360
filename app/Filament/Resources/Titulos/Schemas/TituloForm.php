<?php

namespace App\Filament\Resources\Titulos\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class TituloForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('contrato_id')
                    ->required()
                    ->numeric(),
                DatePicker::make('vencimento')
                    ->required(),
                TextInput::make('valor')
                    ->required()
                    ->numeric(),
                TextInput::make('valor_pago')
                    ->numeric(),
                TextInput::make('status')
                    ->required()
                    ->default('pendente'),
                Textarea::make('pix_copia_e_cola')
                    ->columnSpanFull(),
            ]);
    }
}

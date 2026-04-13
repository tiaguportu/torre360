<?php

namespace App\Filament\Resources\Bancos\Schemas;

use App\Models\CodigoBacen;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class BancoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nome')
                    ->label('Apelido da Conta / Banco')
                    ->placeholder('Ex: ITAU - CONTA CORRENTE')
                    ->required(),

                Select::make('codigo_bacen_id')
                    ->label('Banco (BACEN)')
                    ->relationship('codigoBacen', 'nome_extenso')
                    ->getOptionLabelFromRecordUsing(fn (CodigoBacen $record) => "{$record->codigo} - {$record->nome_extenso}")
                    ->searchable()
                    ->preload()
                    ->required(),

                TextInput::make('agencia')
                    ->label('Agência'),
                TextInput::make('conta')
                    ->label('Número da Conta'),
                TextInput::make('pix_key')
                    ->label('Chave PIX'),
                Toggle::make('is_active')
                    ->label('Ativo')
                    ->default(true)
                    ->required(),
            ]);
    }
}

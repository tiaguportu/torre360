<?php

namespace App\Filament\Resources\ResponsavelFinanceiros\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ResponsavelFinanceiroForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('contrato_id')
                    ->required()
                    ->numeric(),
                Select::make('pessoa_id')
                    ->relationship('pessoa', 'nome', modifyQueryUsing: fn (Builder $query) => $query->whereHas('perfis', fn ($q) => $q->where('nome', 'like', '%Respons_vel%')->orWhere('nome', 'like', '%Responsavel%')))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->label('Responsável Financeiro'),
                TextInput::make('percentual')
                    ->required()
                    ->numeric()
                    ->default(100),
            ]);
    }
}

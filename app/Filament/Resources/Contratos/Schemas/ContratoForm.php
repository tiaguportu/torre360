<?php

namespace App\Filament\Resources\Contratos\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class ContratoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('matricula_id')
                    ->relationship('matricula', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->pessoa?->nome ?? "Matrícula #{$record->id}")
                    ->searchable()
                    ->required(),
                TextInput::make('valor_total')
                    ->numeric()
                    ->prefix('R$')
                    ->required(),
                DatePicker::make('data_aceite'),
                Textarea::make('log_assinatura')
                    ->columnSpanFull(),
                \Filament\Forms\Components\Repeater::make('responsaveisFinanceiros')
                    ->relationship('responsaveisFinanceiros')
                    ->schema([
                        Select::make('pessoa_id')
                            ->relationship('pessoa', 'nome', modifyQueryUsing: fn (\Illuminate\Database\Eloquent\Builder $query) => $query->whereHas('perfis', fn ($q) => $q->where('nome', 'like', '%Respons_vel%')->orWhere('nome', 'like', '%Responsavel%')))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->label('Responsável Financeiro'),
                        TextInput::make('percentual')
                            ->required()
                            ->numeric()
                            ->default(100),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}

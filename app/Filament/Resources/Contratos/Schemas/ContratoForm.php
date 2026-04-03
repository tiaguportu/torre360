<?php

namespace App\Filament\Resources\Contratos\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ContratoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('matriculas')
                    ->relationship('matriculas', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->codigo} - ".($record->pessoa?->nome ?? 'Sem nome'))
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->required()
                    ->label('Alunos (Matrículas)'),
                TextInput::make('valor_total')
                    ->numeric()
                    ->prefix('R$')
                    ->required(),
                DatePicker::make('data_aceite'),
                Textarea::make('log_assinatura')
                    ->columnSpanFull(),
                Repeater::make('responsaveisFinanceiros')
                    ->relationship('responsaveisFinanceiros')
                    ->schema([
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
                    ])
                    ->columnSpanFull(),
            ]);
    }
}

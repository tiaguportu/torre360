<?php

namespace App\Filament\Resources\Funcionarios\Schemas;

use App\Enums\RegimeContratacao;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FuncionarioForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Funcionário')
                    ->columns(2)
                    ->schema([
                        Select::make('pessoa_id')
                            ->label('Pessoa')
                            ->relationship('pessoa', 'nome')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpanFull(),
                        TextInput::make('cargo')
                            ->label('Cargo')
                            ->required()
                            ->maxLength(255),
                        Select::make('regime')
                            ->label('Regime')
                            ->options(RegimeContratacao::class)
                            ->required(),
                        DatePicker::make('data_admissao')
                            ->label('Data de Admissão')
                            ->native(false)
                            ->required(),
                        DatePicker::make('data_desligamento')
                            ->label('Data de Desligamento')
                            ->native(false),
                        TextInput::make('carga_horaria_semanal')
                            ->label('Carga Horária Semanal (h)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(80),
                        Select::make('unidade_id')
                            ->label('Unidade de Lotação')
                            ->relationship('unidade', 'nome')
                            ->searchable()
                            ->preload(),
                    ]),
            ]);
    }
}

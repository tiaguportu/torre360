<?php

namespace App\Filament\Resources\Funcionarios\Schemas;

use App\Enums\RegimeContratacao;
use App\Models\ContratoTrabalho;
use App\Models\Funcionario;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
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
                            ->required()
                            ->visibleOn('create'),
                        CamposDeContrato::salario('salario_inicial', 'Salário Inicial (R$)')
                            ->nullable()
                            ->helperText('Cria o primeiro contrato de trabalho com início na data de admissão. Reajustes depois são registrados como aditivo na aba Contratos de Trabalho.')
                            ->visible(fn (string $operation): bool => $operation === 'create' && (bool) auth()->user()?->can('create', ContratoTrabalho::class)),
                        TextEntry::make('data_admissao_display')
                            ->label('Data de Admissão')
                            ->state(fn (?Funcionario $record): string => $record?->data_admissao?->format('d/m/Y') ?? '—')
                            ->helperText('Para corrigir um erro, use a ação Corrigir data de admissão no topo da página.')
                            ->visibleOn('edit'),
                        TextEntry::make('data_desligamento_display')
                            ->label('Data de Desligamento')
                            ->state(fn (?Funcionario $record): string => $record?->data_desligamento?->format('d/m/Y') ?? '—')
                            ->helperText('Registrado ao concluir um desligamento na aba Admissão e Desligamento.')
                            ->visibleOn('edit'),
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

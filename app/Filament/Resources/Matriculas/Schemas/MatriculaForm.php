<?php

namespace App\Filament\Resources\Matriculas\Schemas;

use App\Enums\SituacaoMatricula;
use App\Filament\Resources\Turmas\Schemas\TurmaForm;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Turma;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class MatriculaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('pessoa_id')
                    ->label('Aluno')
                    ->relationship('pessoa', 'nome', modifyQueryUsing: fn (Builder $query) => $query->whereNotNull('nome')
                        ->where(function ($q) {
                            $q->whereDoesntHave('users')
                                ->orWhereHas('users', fn ($q) => $q->role('aluno'));
                        }))
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->nome.($record->cpf ? " - {$record->cpf}" : ''))
                    ->searchable(['nome', 'cpf'])
                    ->preload()
                    ->required()
                    ->createOptionForm([
                        TextInput::make('nome')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('cpf')
                            ->label('CPF')
                            ->unique(ignoreRecord: true)
                            ->maxLength(11)
                            ->dehydrateStateUsing(fn (?string $state) => $state ? preg_replace('/\D/', '', $state) : null),
                        TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        Select::make('sexo')
                            ->relationship('sexo', 'nome', fn ($query) => $query->whereNotNull('nome'))
                            ->searchable()
                            ->preload(),
                        Select::make('corRaca')
                            ->relationship('corRaca', 'nome', fn ($query) => $query->whereNotNull('nome'))
                            ->searchable()
                            ->preload(),
                    ]),
                // O período da matrícula é o da turma: este campo só filtra as turmas oferecidas (não é gravado).
                Select::make('periodo_letivo_filtro')
                    ->label('Período Letivo')
                    ->options(fn () => PeriodoLetivo::query()->whereNotNull('nome')->orderByDesc('data_inicio')->pluck('nome', 'id'))
                    ->afterStateHydrated(function (Select $component, ?Matricula $record): void {
                        if ($record && $component->getState() === null) {
                            $component->state($record->turma?->periodo_letivo_id);
                        }
                    })
                    ->searchable()
                    ->preload()
                    ->live()
                    ->dehydrated(false)
                    ->helperText('Filtra as turmas abaixo; o período da matrícula é sempre o da turma escolhida.'),
                Select::make('turma_id')
                    ->relationship('turma', 'nome', fn ($query, $record, $livewire, Get $get) => $query
                        ->whereNotNull('nome')
                        ->when($get('periodo_letivo_filtro'), fn ($q, $periodoId) => $q->where('periodo_letivo_id', $periodoId))
                        // Só turmas abertas para matrícula, mas sempre mantendo a turma atual do registro
                        // (ou a turma dona do relation manager) para que o campo não fique em branco.
                        ->abertasParaMatricula([
                            $record?->turma_id,
                            $livewire instanceof RelationManager && $livewire->getOwnerRecord() instanceof Turma
                                ? $livewire->getOwnerRecord()->getKey()
                                : null,
                        ]))
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->nome ?? "Turma #{$record->id}")
                    ->searchable()
                    ->preload()
                    ->createOptionForm(fn (Schema $schema) => TurmaForm::configure($schema)->getComponents())
                    ->required()
                    ->disabled(fn ($livewire) => $livewire instanceof RelationManager && $livewire->getOwnerRecord() instanceof Turma)
                    ->dehydrated(),
                Select::make('situacao')
                    ->label('Situação')
                    ->options(SituacaoMatricula::class)
                    ->required()
                    ->native(false)
                    ->preload()
                    ->searchable(),
                DatePicker::make('data_ativacao')
                    ->label('Data de Ativação')
                    ->native(false)
                    ->displayFormat('d/m/Y'),
                DatePicker::make('data_desativacao')
                    ->label('Data de Desativação')
                    ->native(false)
                    ->displayFormat('d/m/Y'),
            ]);
    }
}

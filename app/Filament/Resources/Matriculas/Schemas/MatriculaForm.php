<?php

namespace App\Filament\Resources\Matriculas\Schemas;

use App\Filament\Resources\SituacaoMatriculas\Schemas\SituacaoMatriculaForm;
use App\Filament\Resources\Turmas\Schemas\TurmaForm;
use App\Models\Turma;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class MatriculaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('pessoa')
                    ->label('Aluno')
                    ->relationship('pessoa', 'nome', modifyQueryUsing: fn (Builder $query) => $query->whereNotNull('nome')->whereHas('users', fn ($q) => $q->role('aluno')))
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->nome.($record->cpf ? " - {$record->cpf}" : ''))
                    ->searchable(['nome', 'cpf'])
                    ->preload()
                    ->required()
                    ->createOptionForm([
                        TextInput::make('nome')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('cpf')
                            ->unique(ignoreRecord: true)
                            ->maxLength(14),
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
                Select::make('periodoLetivo')
                    ->relationship('periodoLetivo', 'nome', fn ($query) => $query->whereNotNull('nome'))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->label('Período Letivo'),
                Select::make('turma')
                    ->relationship('turma', 'nome', fn ($query) => $query->whereNotNull('nome'))
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->nome ?? "Turma #{$record->id}")
                    ->searchable()
                    ->preload()
                    ->createOptionForm(fn (Schema $schema) => TurmaForm::configure($schema)->getComponents())
                    ->required()
                    ->disabled(fn ($livewire) => $livewire instanceof RelationManager && $livewire->getOwnerRecord() instanceof Turma)
                    ->dehydrated(),
                Select::make('situacaoMatricula')
                    ->relationship('situacaoMatricula', 'nome', fn ($query) => $query->whereNotNull('nome'))
                    ->searchable()
                    ->preload()
                    ->createOptionForm(fn (Schema $schema) => SituacaoMatriculaForm::configure($schema)->getComponents())
                    ->required()
                    ->label('Situação'),
            ]);
    }
}

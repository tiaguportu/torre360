<?php

namespace App\Filament\Resources\Matriculas\Schemas;

use App\Enums\SituacaoMatricula;
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
                Select::make('periodo_letivo_id')
                    ->relationship('periodoLetivo', 'nome', fn ($query) => $query->whereNotNull('nome'))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->label('Período Letivo'),
                Select::make('turma_id')
                    ->relationship('turma', 'nome', fn ($query) => $query->whereNotNull('nome'))
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
            ]);
    }
}

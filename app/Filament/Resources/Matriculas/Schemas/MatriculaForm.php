<?php

namespace App\Filament\Resources\Matriculas\Schemas;

use App\Filament\Resources\SituacaoMatriculas\Schemas\SituacaoMatriculaForm;
use App\Filament\Resources\Turmas\Schemas\TurmaForm;
use App\Models\Pais;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;

class MatriculaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('pessoa_id')
                    ->relationship('pessoa', 'nome', modifyQueryUsing: fn (\Illuminate\Database\Eloquent\Builder $query) => $query->whereNotNull('nome')->whereHas('perfis', fn ($q) => $q->where('nome', 'like', '%Aluno%')))
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->nome . ($record->cpf ? " - {$record->cpf}" : ""))
                    ->searchable(['nome', 'cpf'])
                    ->preload()
                    ->required()
                    ->label('Aluno')
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
                        Select::make('sexo_id')
                            ->relationship('sexo', 'nome', fn ($query) => $query->whereNotNull('nome'))
                            ->searchable()
                            ->preload(),
                        Select::make('cor_raca_id')
                            ->relationship('corRaca', 'nome', fn ($query) => $query->whereNotNull('nome'))
                            ->searchable()
                            ->preload(),
                    ]),
                Select::make('turma_id')
                    ->relationship('turma', 'nome', fn ($query) => $query->whereNotNull('nome'))
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->nome ?? "Turma #{$record->id}")
                    ->searchable()
                    ->preload()
                    ->createOptionForm(fn (Schema $schema) => TurmaForm::configure($schema)->getComponents())
                    ->required(),
                Select::make('situacao_matricula_id')
                    ->relationship('situacaoMatricula', 'nome', fn ($query) => $query->whereNotNull('nome'))
                    ->searchable()
                    ->preload()
                    ->createOptionForm(fn (Schema $schema) => SituacaoMatriculaForm::configure($schema)->getComponents())
                    ->required()
                    ->label('Situação'),
                DatePicker::make('data_matricula')
                    ->required(),
            ]);
    }
}

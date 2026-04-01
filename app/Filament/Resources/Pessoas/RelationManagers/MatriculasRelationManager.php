<?php

namespace App\Filament\Resources\Pessoas\RelationManagers;

use App\Filament\Resources\SituacaoMatriculas\Schemas\SituacaoMatriculaForm;
use App\Filament\Resources\Turmas\Schemas\TurmaForm;
use App\Models\SituacaoMatricula;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MatriculasRelationManager extends RelationManager
{
    protected static string $relationship = 'matriculas';

    protected static ?string $title = 'Matrículas';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('turma_id')
                    ->relationship('turma', 'nome', fn ($query) => $query->whereNotNull('nome'))
                    ->searchable()
                    ->preload()
                    ->createOptionForm(fn (Schema $schema) => TurmaForm::configure($schema)->getComponents())
                    ->required(),
                Select::make('situacao_matricula_id')
                    ->relationship('situacaoMatricula', 'nome', fn ($query) => $query->whereNotNull('nome'))
                    ->default(fn () => SituacaoMatricula::where('nome', 'Ativa')->value('id'))
                    ->searchable()
                    ->preload()
                    ->createOptionForm(fn (Schema $schema) => SituacaoMatriculaForm::configure($schema)->getComponents())
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('turma.nome')
                    ->label('Turma')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('situacaoMatricula.nome')
                    ->label('Situação')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Ativa' => 'success',
                        'Inativa' => 'danger',
                        'Cancelada' => 'warning',
                        'Trancada' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Data Matrícula')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Series\RelationManagers;

use App\Filament\Resources\Pessoas\Schemas\PessoaForm;
use App\Filament\Resources\Turnos\Schemas\TurnoForm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TurmasRelationManager extends RelationManager
{
    protected static string $relationship = 'turmas';

    protected static ?string $title = 'Turmas';

    protected static ?string $recordTitleAttribute = 'nome';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nome')
                    ->required()
                    ->maxLength(255),
                Select::make('turno_id')
                    ->relationship('turno', 'nome')
                    ->searchable()
                    ->preload()
                    ->createOptionForm(fn (Schema $schema) => TurnoForm::configure($schema)->getComponents())
                    ->required(),
                Select::make('professor_conselheiro_id')
                    ->relationship('professorConselheiro', 'nome')
                    ->searchable(['nome', 'cpf'])
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->nome.($record->cpf ? " - {$record->cpf}" : ''))
                    ->preload()
                    ->createOptionForm(fn (Schema $schema) => PessoaForm::configure($schema)->getComponents()),
                TextInput::make('vagas_maximas')
                    ->numeric()
                    ->default(30),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('turno.nome')
                    ->label('Turno')
                    ->sortable(),
                TextColumn::make('professorConselheiro.nome')
                    ->label('Professor Conselheiro')
                    ->sortable(),
                TextColumn::make('vagas_maximas')
                    ->label('Vagas')
                    ->numeric()
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

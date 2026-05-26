<?php

namespace App\Filament\Resources\Turmas\RelationManagers;

use App\Models\Disciplina;
use App\Models\Pessoa;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DisciplinasRelationManager extends RelationManager
{
    protected static string $relationship = 'disciplinas';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nome')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nome')
            ->columns([
                TextColumn::make('nome')
                    ->searchable(),
                TextColumn::make('pivot.professor.nome')
                    ->label('Professor Responsável')
                    ->placeholder('Regente da Turma')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->schema([
                        TextInput::make('nome')
                            ->required()
                            ->maxLength(255),
                        Select::make('professor_id')
                            ->label('Professor Responsável')
                            ->options(Pessoa::all()->pluck('nome', 'id'))
                            ->searchable()
                            ->preload(),
                    ])
                    ->after(function (Disciplina $record, array $data): void {
                        $this->getOwnerRecord()->disciplinas()->updateExistingPivot($record->id, [
                            'professor_id' => $data['professor_id'] ?? null,
                        ]);
                    }),
                AttachAction::make()
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Select::make('professor_id')
                            ->label('Professor Responsável')
                            ->options(Pessoa::all()->pluck('nome', 'id'))
                            ->searchable()
                            ->preload(),
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->schema([
                        Select::make('professor_id')
                            ->label('Professor Responsável')
                            ->options(Pessoa::all()->pluck('nome', 'id'))
                            ->searchable()
                            ->preload(),
                    ])
                    ->mutateRecordDataUsing(function (array $data, Disciplina $record): array {
                        $data['professor_id'] = $record->pivot?->professor_id;

                        return $data;
                    })
                    ->action(function (Disciplina $record, array $data): void {
                        $this->getOwnerRecord()->disciplinas()->updateExistingPivot($record->id, [
                            'professor_id' => $data['professor_id'] ?? null,
                        ]);
                    }),
                DetachAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->stackedOnMobile();
    }
}

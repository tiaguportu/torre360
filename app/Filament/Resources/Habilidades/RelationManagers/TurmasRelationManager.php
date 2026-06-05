<?php

namespace App\Filament\Resources\Habilidades\RelationManagers;

use App\Models\Pessoa;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TurmasRelationManager extends RelationManager
{
    protected static string $relationship = 'turmas';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('professor_id')
                    ->label('Professor Responsável')
                    ->options(Pessoa::all()->pluck('nome', 'id'))
                    ->searchable()
                    ->preload(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nome')
            ->columns([
                TextColumn::make('nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('serie.nome')
                    ->label('Série')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('turno.nome')
                    ->label('Turno')
                    ->sortable(),
                TextColumn::make('pivot.professor_id')
                    ->label('Professor Responsável')
                    ->formatStateUsing(fn ($state) => Pessoa::find($state)?->nome ?? 'Regente da Turma')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->form(fn (AttachAction $action): array => [
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
                    ->label('Editar Vínculo'),
                DetachAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ])
            ->stackedOnMobile();
    }
}

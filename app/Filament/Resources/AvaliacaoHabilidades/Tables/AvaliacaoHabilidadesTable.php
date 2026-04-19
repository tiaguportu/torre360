<?php

namespace App\Filament\Resources\AvaliacaoHabilidades\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AvaliacaoHabilidadesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('matricula.pessoa.nome')
                    ->label('Aluno')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('matricula.turma.nome')
                    ->label('Turma')
                    ->sortable(),
                TextColumn::make('habilidade.nome')
                    ->label('Habilidade')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->habilidade?->nome)
                    ->sortable(),
                TextColumn::make('etapaAvaliativa.nome')
                    ->label('Etapa'),
                TextColumn::make('conceito')
                    ->badge()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Último Lançamento')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Notas\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NotasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('avaliacao.label_exibicao')
                    ->label('Avaliação')
                    ->searchable(),
                TextColumn::make('matricula')
                    ->label('Matrícula')
                    ->formatStateUsing(function ($record): string {
                        $matricula = $record->matricula;
                        if (! $matricula) {
                            return 'N/A';
                        }

                        return "{$matricula->turma?->nome} - {$matricula->periodoLetivo?->nome} - {$matricula->pessoa?->nome}";
                    })
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHas('matricula', function ($query) use ($search) {
                            $query->whereHas('turma', fn ($q) => $q->where('nome', 'like', "%{$search}%"))
                                ->orWhereHas('periodoLetivo', fn ($q) => $q->where('nome', 'like', "%{$search}%"))
                                ->orWhereHas('pessoa', fn ($q) => $q->where('nome', 'like', "%{$search}%"));
                        });
                    }),
                TextColumn::make('valor')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->stackedOnMobile();
    }
}

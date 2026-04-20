<?php

namespace App\Filament\Resources\CategoriaAvaliacaos\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class CategoriaAvaliacaosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('descricao')
                    ->label('Descrição')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('ordem_boletim')
                    ->label('Ordem Boletim')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('substituicao.nome')
                    ->label('Substitui')
                    ->placeholder('—')
                    ->badge()
                    ->color('warning'),
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
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function (DeleteBulkAction $action, Collection $records) {
                            $comVinculo = $records->filter(fn ($record) => $record->avaliacaos()->exists());

                            if ($comVinculo->isNotEmpty()) {
                                Notification::make()
                                    ->danger()
                                    ->title('Não é possível excluir em lote')
                                    ->body("As seguintes categorias possuem avaliações vinculadas: {$comVinculo->pluck('nome')->implode(', ')}. Remova as avaliações antes de excluir.")
                                    ->send();

                                $action->halt();
                            }
                        }),
                ]),
            ])
            ->defaultSort('ordem_boletim')
            ->stackedOnMobile();
    }
}

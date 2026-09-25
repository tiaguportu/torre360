<?php

namespace App\Filament\Resources\Disciplinas\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class DisciplinasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('areaConhecimento.nome')
                    ->label('Área')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sigla')
                    ->searchable(),
                IconColumn::make('flag_matricula_automatica')
                    ->label('Matr. Automática')
                    ->boolean(),
                TextColumn::make('carga_horaria_semanal')
                    ->label('CH Semanal')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('ordem_boletim')
                    ->label('Ordem Boletim')
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
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function (DeleteBulkAction $action, Collection $records) {
                            $comVinculo = $records->filter(fn ($record) => $record->possuiNotasVinculadas());

                            if ($comVinculo->isNotEmpty()) {
                                Notification::make()
                                    ->danger()
                                    ->title('Não é possível excluir em lote')
                                    ->body("As seguintes disciplinas possuem notas lançadas vinculadas: {$comVinculo->pluck('nome')->implode(', ')}. Remova as notas antes de excluir.")
                                    ->send();

                                $action->halt();
                            }
                        }),
                ]),
            ])
            ->defaultSort('ordem_boletim', 'asc')
            ->stackedOnMobile();
    }
}

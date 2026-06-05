<?php

namespace App\Filament\Resources\AvaliacaoHabilidades\Tables;

use App\Filament\Resources\AvaliacaoHabilidades\AvaliacaoHabilidadeResource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AvaliacaoHabilidadesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('turma.nome')
                    ->label('Turma')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('etapaAvaliativa.nome')
                    ->label('Etapa')
                    ->sortable(),
                TextColumn::make('professor.nome')
                    ->label('Professor')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('updated_at')
                    ->label('Última Atualização')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
                Action::make('lancarNotas')
                    ->label('Lançar Notas')
                    ->icon('heroicon-o-pencil-square')
                    ->color('success')
                    ->url(fn ($record): string => AvaliacaoHabilidadeResource::getUrl('lancar-notas', ['record' => $record->id]))
                    ->visible(fn ($record): bool => auth()->user()->can('Update:AvaliacaoHabilidade', $record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->stackedOnMobile();
    }
}

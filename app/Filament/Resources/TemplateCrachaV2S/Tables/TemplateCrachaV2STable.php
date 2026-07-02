<?php

namespace App\Filament\Resources\TemplateCrachaV2S\Tables;

use App\Models\TemplateCrachaV2;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TemplateCrachaV2STable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('tipo_entidade')
                    ->label('Tipo de Entidade')
                    ->badge()
                    ->sortable(),
                TextColumn::make('dimensoes')
                    ->label('Dimensões')
                    ->state(fn (TemplateCrachaV2 $record): string => "{$record->largura}x{$record->altura} px"),
                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('editar_canvas')
                    ->label('Editar Canvas')
                    ->icon('heroicon-o-paint-brush')
                    ->color('success')
                    ->url(fn (TemplateCrachaV2 $record): string => route('template-crachas-v2.editor', $record->id))
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->stackedOnMobile();
    }
}

<?php

namespace App\Filament\Resources\MensagemWhatsappTemplates\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MensagemWhatsappTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')
                    ->label('Nome')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('conteudo')
                    ->label('Mensagem')
                    ->limit(60)
                    ->wrap(),
                IconColumn::make('ativo')
                    ->label('Ativo')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->stackedOnMobile();
    }
}

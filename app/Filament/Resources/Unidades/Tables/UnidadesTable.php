<?php

namespace App\Filament\Resources\Unidades\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UnidadesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('cnpj')
                    ->searchable(),
                TextColumn::make('codigo_inep')
                    ->label('Código INEP')
                    ->searchable(),
                TextColumn::make('situacao_funcionamento')
                    ->label('Situação')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        '1' => '1-Em atividade',
                        '2' => '2-Paralisada',
                        '3' => '3-Extinta',
                        default => $state ?? '-',
                    })
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        '1' => 'success',
                        '2' => 'warning',
                        '3' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('endereco.logradouro')
                    ->label('Endereço')
                    ->sortable()
                    ->toggleable(),
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
                    DeleteBulkAction::make(),
                ]),
            ])
            ->stackedOnMobile();
    }
}

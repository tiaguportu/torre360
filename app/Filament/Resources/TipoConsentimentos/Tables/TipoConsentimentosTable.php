<?php

namespace App\Filament\Resources\TipoConsentimentos\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TipoConsentimentosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('exige_renovacao_periodica')
                    ->label('Renovação Periódica')
                    ->boolean(),
                TextColumn::make('periodicidade_meses')
                    ->label('A cada (meses)')
                    ->placeholder('—'),
                IconColumn::make('is_ativo')
                    ->label('Ativo')
                    ->boolean(),
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

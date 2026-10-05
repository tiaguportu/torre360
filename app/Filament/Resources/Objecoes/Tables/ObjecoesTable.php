<?php

declare(strict_types=1);

namespace App\Filament\Resources\Objecoes\Tables;

use App\Models\Objecao;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ObjecoesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('ordem')
            ->columns([
                TextColumn::make('ordem')
                    ->label('#')
                    ->sortable()
                    ->width('60px'),

                TextColumn::make('titulo')
                    ->label('Objeção')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('categoria')
                    ->label('Categoria')
                    ->badge()
                    ->color(fn (string $state): string => Objecao::CORES_CATEGORIAS[$state] ?? 'gray')
                    ->formatStateUsing(fn (string $state): string => Objecao::CATEGORIAS[$state] ?? ucfirst($state))
                    ->sortable(),

                TextColumn::make('descricao')
                    ->label('Como a família fala')
                    ->limit(60)
                    ->tooltip(fn (Objecao $record): string => $record->descricao),

                TextColumn::make('resposta_sugerida')
                    ->label('Roteiro Sugerido')
                    ->limit(70)
                    ->tooltip(fn (Objecao $record): string => $record->resposta_sugerida),

                IconColumn::make('is_ativo')
                    ->label('Ativa')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('categoria')
                    ->label('Categoria da Objeção')
                    ->options(Objecao::CATEGORIAS),

                TernaryFilter::make('is_ativo')
                    ->label('Status')
                    ->trueLabel('Apenas Objeções Ativas')
                    ->falseLabel('Apenas Inativas'),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->stackedOnMobile();
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Resources\IndicadorMetas\Tables;

use App\Filament\Resources\IndicadorMetas\Schemas\IndicadorMetaForm;
use App\Models\IndicadorMeta;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IndicadorMetasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('unidade'))
            ->columns([
                TextColumn::make('indicador')->label('Indicador')->formatStateUsing(fn (string $state): string => IndicadorMetaForm::opcoesDeIndicador()[$state] ?? $state)->searchable()->wrap(),
                TextColumn::make('ano')->label('Ano')->sortable(),
                TextColumn::make('unidade_id')->label('Unidade')->state(fn (IndicadorMeta $record): string => $record->unidade?->nome ?? 'Escola toda'),
                TextColumn::make('meta')->label('Meta')->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.'),
                TextColumn::make('tolerancia_percentual')->label('Tolerância')->suffix('%')->numeric(decimalPlaces: 1, decimalSeparator: ',', thousandsSeparator: '.'),
            ])
            ->filters([
                SelectFilter::make('ano')->label('Ano')->options(fn (): array => IndicadorMeta::query()->distinct()->orderByDesc('ano')->pluck('ano', 'ano')->all()),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->defaultSort('ano', 'desc')
            ->stackedOnMobile();
    }
}

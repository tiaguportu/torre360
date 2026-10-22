<?php

declare(strict_types=1);

namespace App\Filament\Resources\ListaMaterialEscolars\Tables;

use App\Enums\StatusListaMaterial;
use App\Models\ListaMaterialEscolar;
use App\Models\PeriodoLetivo;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ListaMaterialEscolarsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('periodo_letivo_id', 'desc')
            ->columns([
                TextColumn::make('serie.nome')->label('Série')->description(fn (ListaMaterialEscolar $record): ?string => $record->serie?->curso?->nome_externo)->weight('bold')->searchable(),
                TextColumn::make('periodoLetivo.nome')->label('Período'),
                TextColumn::make('itens_count')->label('Itens')->numeric(),
                TextColumn::make('status')->label('Situação')->badge(),
                TextColumn::make('publicada_em')->label('Publicada em')->dateTime('d/m/Y H:i')->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('periodo_letivo_id')->label('Período')->options(fn (): array => PeriodoLetivo::query()->orderByDesc('data_inicio')->pluck('nome', 'id')->all()),
                SelectFilter::make('status')->label('Situação')->options(StatusListaMaterial::class),
            ])
            ->recordActions([
                EditAction::make()->label('Abrir'),
                DeleteAction::make()->visible(fn (ListaMaterialEscolar $record): bool => ! $record->estaPublicada() && (bool) auth()->user()?->can('delete', $record)),
            ])
            ->stackedOnMobile();
    }
}

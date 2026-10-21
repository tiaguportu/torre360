<?php

declare(strict_types=1);

namespace App\Filament\Resources\CenarioReajustes\Tables;

use App\Enums\StatusCenarioReajuste;
use App\Models\CenarioReajuste;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CenarioReajustesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('nome')->label('Cenário')->searchable()->weight('bold'),
                TextColumn::make('periodoRematricula.nome')->label('Campanha'),
                TextColumn::make('percentual_padrao')->label('Percentual')->suffix('%')->numeric(2),
                TextColumn::make('itens_count')->label('Séries')->numeric(),
                TextColumn::make('status')->label('Situação')->badge(),
                TextColumn::make('comunicado_em')->label('Comunicado')->dateTime('d/m/Y')->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Situação')->options(StatusCenarioReajuste::class),
            ])
            ->recordActions([
                ViewAction::make()->label('Abrir'),
                EditAction::make()->visible(fn (CenarioReajuste $record): bool => (bool) auth()->user()?->can('update', $record)),
                DeleteAction::make()->visible(fn (CenarioReajuste $record): bool => (bool) auth()->user()?->can('delete', $record)),
            ])
            ->stackedOnMobile();
    }
}

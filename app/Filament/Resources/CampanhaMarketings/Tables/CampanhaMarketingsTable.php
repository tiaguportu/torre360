<?php

namespace App\Filament\Resources\CampanhaMarketings\Tables;

use App\Models\CampanhaMarketing;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CampanhaMarketingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                'interessados as leads_count',
                'interessados as matriculados_count' => fn (Builder $q) => $q->whereNotNull('data_conversao'),
            ]))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('nome')
                    ->label('Campanha')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('canal')
                    ->label('Canal')
                    ->formatStateUsing(fn (?string $state): string => CampanhaMarketing::CANAIS[$state] ?? ($state ?? '—'))
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                TextColumn::make('codigo_utm')
                    ->label('UTM')
                    ->copyable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('leads_count')
                    ->label('Leads')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                TextColumn::make('matriculados_count')
                    ->label('Matrículas')
                    ->badge()
                    ->color('success')
                    ->sortable(),
                TextColumn::make('taxa_conversao')
                    ->label('Conversão')
                    ->state(fn (CampanhaMarketing $record): string => $record->leads_count > 0
                        ? number_format($record->matriculados_count / $record->leads_count * 100, 1, ',', '.').'%'
                        : '—'),
                TextColumn::make('custo')
                    ->label('Investimento')
                    ->money('BRL')
                    ->sortable(),
                TextColumn::make('data_inicio')
                    ->label('Início')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('data_fim')
                    ->label('Término')
                    ->date('d/m/Y')
                    ->placeholder('Em aberto')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('ativa')
                    ->label('Ativa')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('canal')
                    ->options(CampanhaMarketing::CANAIS),
                TernaryFilter::make('ativa')
                    ->label('Ativa'),
            ])
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

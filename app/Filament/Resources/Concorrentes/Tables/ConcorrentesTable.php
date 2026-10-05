<?php

declare(strict_types=1);

namespace App\Filament\Resources\Concorrentes\Tables;

use App\Models\Concorrente;
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

class ConcorrentesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')
                    ->label('Escola Concorrente')
                    ->description(fn (Concorrente $record): ?string => $record->sigla ? "Sigla: {$record->sigla}" : null)
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('cidade.nome')
                    ->label('Localização')
                    ->description(fn (Concorrente $record): ?string => $record->bairro)
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('faixa_preco')
                    ->label('Faixa de Mensalidade')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'mais_barato' => 'warning',
                        'equivalente' => 'info',
                        'mais_caro' => 'purple',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => Concorrente::FAIXAS_PRECO[$state] ?? '—'),

                TextColumn::make('mensalidade_estimada')
                    ->label('Mensalidade Est.')
                    ->money('BRL')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('interessados_perdidos_count')
                    ->counts('interessadosPerdidos')
                    ->label('Perdas Registradas')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'rose' : 'gray')
                    ->suffix(' leads')
                    ->sortable(),

                TextColumn::make('proposta_pedagogica')
                    ->label('Linha Pedagógica')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_ativo')
                    ->label('Ativo no Radar')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('faixa_preco')
                    ->label('Faixa de Mensalidade')
                    ->options(Concorrente::FAIXAS_PRECO),

                TernaryFilter::make('is_ativo')
                    ->label('Status do Radar')
                    ->trueLabel('Apenas Concorrentes Ativos')
                    ->falseLabel('Apenas Inativos'),
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

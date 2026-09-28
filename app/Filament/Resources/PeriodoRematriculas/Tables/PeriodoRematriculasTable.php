<?php

namespace App\Filament\Resources\PeriodoRematriculas\Tables;

use App\Models\PeriodoRematricula;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PeriodoRematriculasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')
                    ->label('Campanha')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('periodoLetivoOrigem.nome')
                    ->label('Origem (Atual)')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('periodoLetivoDestino.nome')
                    ->label('Destino (Próximo)')
                    ->badge()
                    ->color('primary'),

                TextColumn::make('data_inicio')
                    ->label('Início')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('data_fim')
                    ->label('Término')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('status_vigencia')
                    ->label('Situação')
                    ->state(function (PeriodoRematricula $record): string {
                        if (! $record->is_ativo) {
                            return 'Inativo';
                        }
                        if ($record->isAberto()) {
                            return 'Aberto / No Prazo';
                        }
                        if (now()->isBefore($record->data_inicio)) {
                            return 'Em Breve';
                        }

                        return 'Encerrado';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Aberto / No Prazo' => 'success',
                        'Em Breve' => 'warning',
                        'Encerrado' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('rematriculas_count')
                    ->label('Rematrículas')
                    ->counts('rematriculas')
                    ->badge()
                    ->color('info'),

                IconColumn::make('is_ativo')
                    ->label('Ativo')
                    ->boolean()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->stackedOnMobile();
    }
}

<?php

namespace App\Filament\Resources\Inventarios\Tables;

use App\Filament\Resources\Inventarios\InventarioResource;
use App\Models\InventarioAcervo;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InventariosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('titulo')
                    ->label('Inventário / Auditoria')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('data_inicio')
                    ->label('Início')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('data_fim')
                    ->label('Conclusão')
                    ->date('d/m/Y')
                    ->placeholder('Em andamento')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'concluido' => 'success',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'concluido' => 'Concluído',
                        default => 'Em Andamento',
                    }),
                TextColumn::make('itens_count')
                    ->counts('itens')
                    ->label('Exemplares Bipados')
                    ->badge()
                    ->color('primary'),
            ])
            ->recordActions([
                Action::make('conferencia')
                    ->label('Auditar / Bipar')
                    ->icon('heroicon-o-qr-code')
                    ->color('primary')
                    ->url(fn (InventarioAcervo $record): string => InventarioResource::getUrl('conferencia', ['record' => $record])),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('data_inicio', 'desc')
            ->stackedOnMobile();
    }
}

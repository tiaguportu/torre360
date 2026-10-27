<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequisicoesCompra\Tables;

use App\Enums\StatusRequisicaoCompra;
use App\Filament\Resources\RequisicoesCompra\RequisicaoCompraResource;
use App\Models\RequisicaoCompra;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RequisicoesCompraTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['solicitante', 'centroCusto', 'pedido.fornecedor', 'itens']))
            ->columns([
                TextColumn::make('numero')->label('Nº')->searchable()->weight('bold')->sortable(),
                TextColumn::make('titulo')->label('Compra')->searchable()->wrap()->description(fn (RequisicaoCompra $record): ?string => $record->solicitante?->name),
                TextColumn::make('centroCusto.nome')->label('Setor')->placeholder('—')->toggleable(),
                TextColumn::make('status')->label('Situação')->badge(),
                TextColumn::make('urgencia')
                    ->label('Urgência')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'urgente' ? 'Urgente' : 'Normal')
                    ->color(fn (string $state): string => $state === 'urgente' ? 'danger' : 'gray')
                    ->toggleable(),
                TextColumn::make('valor')
                    ->label('Valor')
                    ->state(fn (RequisicaoCompra $record): float => $record->pedido ? (float) $record->pedido->valor_total : $record->valorEstimado())
                    ->money('BRL')
                    ->description(fn (RequisicaoCompra $record): string => $record->pedido ? $record->pedido->fornecedor?->razao_social ?? 'Pedido' : 'Estimativa'),
                TextColumn::make('created_at')->label('Criada em')->dateTime('d/m/Y')->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Situação')->options(StatusRequisicaoCompra::class)->multiple(),
            ])
            ->recordActions([ViewAction::make()->label('Abrir')])
            ->recordUrl(fn (RequisicaoCompra $record): string => RequisicaoCompraResource::getUrl('view', ['record' => $record]))
            ->defaultSort('id', 'desc')
            ->stackedOnMobile();
    }
}

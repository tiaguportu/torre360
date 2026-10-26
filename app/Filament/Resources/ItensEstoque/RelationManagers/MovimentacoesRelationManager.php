<?php

declare(strict_types=1);

namespace App\Filament\Resources\ItensEstoque\RelationManagers;

use App\Models\MovimentacaoEstoque;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Histórico de entradas, saídas e ajustes do item (Onda 16, fase 2). Só leitura: a movimentação nasce pelas ações do estoque.
 */
class MovimentacoesRelationManager extends RelationManager
{
    protected static string $relationship = 'movimentacoes';

    protected static ?string $title = 'Movimentações';

    protected static ?string $modelLabel = 'movimentação';

    protected static ?string $pluralModelLabel = 'movimentações';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['usuario', 'centroCusto']))
            ->columns([
                TextColumn::make('created_at')->label('Quando')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('tipo')->label('Tipo')->badge(),
                TextColumn::make('quantidade')
                    ->label('Quantidade')
                    ->numeric(decimalPlaces: 3, decimalSeparator: ',', thousandsSeparator: '.')
                    ->color(fn (MovimentacaoEstoque $record): string => (float) $record->quantidade < 0 ? 'danger' : 'success'),
                TextColumn::make('saldo_apos')->label('Saldo depois')->numeric(decimalPlaces: 3, decimalSeparator: ',', thousandsSeparator: '.'),
                TextColumn::make('centroCusto.nome')->label('Setor')->placeholder('—'),
                TextColumn::make('motivo')->label('Motivo')->limit(60)->placeholder('—')->wrap(),
                TextColumn::make('usuario.name')->label('Por')->placeholder('—'),
            ])
            ->filters([])
            ->defaultSort('id', 'desc')
            ->stackedOnMobile();
    }
}

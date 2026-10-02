<?php

namespace App\Filament\Resources\Funcionarios\Tables;

use App\Enums\RegimeContratacao;
use App\Models\Funcionario;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class FuncionariosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pessoa.nome')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('cargo')
                    ->label('Cargo')
                    ->searchable(),
                TextColumn::make('regime')
                    ->label('Regime')
                    ->badge(),
                TextColumn::make('unidade.nome')
                    ->label('Unidade')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('data_admissao')
                    ->label('Admissão')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('data_desligamento')
                    ->label('Desligamento')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->color('danger')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('regime')
                    ->label('Regime')
                    ->options(RegimeContratacao::class),
                TernaryFilter::make('ativo')
                    ->label('Situação')
                    ->placeholder('Todos')
                    ->trueLabel('Ativos')
                    ->falseLabel('Desligados')
                    ->queries(
                        true: fn ($query) => $query->whereNull('data_desligamento'),
                        false: fn ($query) => $query->whereNotNull('data_desligamento'),
                    ),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('cargo')
            ->stackedOnMobile();
    }

    public static function statusColor(Funcionario $record): string
    {
        return $record->isAtivo() ? 'success' : 'gray';
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProcessosFuncionario\Tables;

use App\Enums\StatusProcessoFuncionario;
use App\Enums\TipoProcessoFuncionario;
use App\Filament\Resources\ProcessosFuncionario\ProcessoFuncionarioResource;
use App\Models\ProcessoFuncionario;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProcessosFuncionarioTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('funcionario.pessoa.nome')
                    ->label('Funcionário')
                    ->description(fn (ProcessoFuncionario $record): ?string => $record->funcionario?->cargo)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge(),
                TextColumn::make('data_prevista')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Situação')
                    ->badge(),
                TextColumn::make('andamento')
                    ->label('Andamento')
                    ->state(fn (ProcessoFuncionario $record): string => "{$record->itens_resolvidos}/{$record->itens_total}")
                    ->description(fn (ProcessoFuncionario $record): ?string => $record->estaEmAndamento() && $record->itens_atrasados > 0 ? "{$record->itens_atrasados} atrasada(s)" : null),
            ])
            ->filters([
                SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options(TipoProcessoFuncionario::class),
                SelectFilter::make('status')
                    ->label('Situação')
                    ->options(StatusProcessoFuncionario::class),
            ])
            ->recordActions([
                ViewAction::make()->label('Abrir'),
            ])
            ->recordUrl(fn (ProcessoFuncionario $record): string => ProcessoFuncionarioResource::getUrl('view', ['record' => $record]))
            ->defaultSort('data_prevista', 'desc')
            ->stackedOnMobile();
    }
}

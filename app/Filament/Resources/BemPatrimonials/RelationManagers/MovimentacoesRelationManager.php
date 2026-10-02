<?php

namespace App\Filament\Resources\BemPatrimonials\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MovimentacoesRelationManager extends RelationManager
{
    protected static string $relationship = 'movimentacoes';

    protected static ?string $title = 'Movimentações';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('data')
            ->defaultSort('data', 'desc')
            ->columns([
                TextColumn::make('data')
                    ->label('Data')
                    ->date('d/m/Y'),
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->formatStateUsing(fn (string $state): string => $state === 'transferencia' ? 'Transferência' : 'Mudança de Status'),
                TextColumn::make('unidadeNova.nome')
                    ->label('Nova Unidade')
                    ->placeholder('—'),
                TextColumn::make('salaNova.nome')
                    ->label('Nova Sala')
                    ->placeholder('—'),
                TextColumn::make('status_novo')
                    ->label('Novo Status')
                    ->placeholder('—'),
                TextColumn::make('registradoPor.name')
                    ->label('Registrado Por')
                    ->placeholder('—'),
                TextColumn::make('observacao')
                    ->label('Observação')
                    ->limit(40)
                    ->placeholder('—'),
            ])
            ->stackedOnMobile();
    }
}

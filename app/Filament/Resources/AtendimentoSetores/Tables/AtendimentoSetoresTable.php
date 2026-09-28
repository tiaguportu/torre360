<?php

namespace App\Filament\Resources\AtendimentoSetores\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AtendimentoSetoresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ordem')
                    ->label('#')
                    ->sortable(),

                TextColumn::make('nome')
                    ->label('Nome do Setor')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('descricao')
                    ->label('Descrição')
                    ->limit(40),

                TextColumn::make('email_notificacao')
                    ->label('E-mail Notificação')
                    ->placeholder('-'),

                TextColumn::make('chamados_count')
                    ->label('Chamados')
                    ->counts('chamados')
                    ->badge()
                    ->color('info'),

                IconColumn::make('ativo')
                    ->label('Ativo')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('ordem', 'asc')
            ->recordActions([
                EditAction::make(),
            ])
            ->stackedOnMobile();
    }
}

<?php

namespace App\Filament\Resources\Pessoas\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PessoasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('foto')
                    ->circular()
                    ->label('')
                    ->width(40)
                    ->height(40)
                    ->defaultImageUrl(fn () => 'https://ui-avatars.com/api/?name=Pessoa&color=7F9CF5&background=EBF4FF')
                    ->getStateUsing(function ($record) {
                        return $record->foto ?: null;
                    }),

                TextColumn::make('nome')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('perfis.nome')
                    ->badge()
                    ->label('Perfis')
                    ->searchable(),

                TextColumn::make('data_nascimento')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('cpf')
                    ->searchable(),

                TextColumn::make('nacionalidade.nome')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('naturalidade.nome')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('endereco.logradouro')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('raca_cor')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

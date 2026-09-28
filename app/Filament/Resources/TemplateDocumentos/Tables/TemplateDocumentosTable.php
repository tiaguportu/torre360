<?php

namespace App\Filament\Resources\TemplateDocumentos\Tables;

use App\Enums\TipoTemplateDocumento;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TemplateDocumentosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')
                    ->label('Modelo')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('tipo')
                    ->label('Tipo de Documento')
                    ->badge()
                    ->sortable(),

                TextColumn::make('validade_dias')
                    ->label('Validade')
                    ->formatStateUsing(fn ($state) => "{$state} dias")
                    ->sortable(),

                IconColumn::make('is_ativo')
                    ->label('Ativo')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('solicitacoes_count')
                    ->label('Emissões')
                    ->counts('solicitacoes')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('updated_at')
                    ->label('Última Alteração')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('tipo')
                    ->label('Tipo de Documento')
                    ->options(TipoTemplateDocumento::class),
                SelectFilter::make('is_ativo')
                    ->label('Situação')
                    ->options([
                        '1' => 'Apenas Ativos',
                        '0' => 'Inativos',
                    ]),
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

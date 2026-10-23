<?php

declare(strict_types=1);

namespace App\Filament\Resources\DocumentoRegulatorios\Tables;

use App\Models\DocumentoRegulatorio;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DocumentoRegulatoriosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')
                    ->label('Documento')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (DocumentoRegulatorio $record): ?string => $record->numero_registro ? "Nº: {$record->numero_registro}" : null),

                TextColumn::make('tipo_documento')
                    ->label('Tipo')
                    ->formatStateUsing(fn (string $state): string => DocumentoRegulatorio::TIPOS[$state] ?? $state)
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('unidade.nome')
                    ->label('Unidade')
                    ->placeholder('Institucional / Todas')
                    ->sortable(),

                TextColumn::make('data_validade')
                    ->label('Validade')
                    ->date('d/m/Y')
                    ->placeholder('Indeterminada')
                    ->sortable()
                    ->color(fn (DocumentoRegulatorio $record): string => match ($record->status) {
                        'vencido' => 'danger',
                        'alerta_vencimento' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('status')
                    ->label('Situação')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => DocumentoRegulatorio::STATUS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'vigente' => 'success',
                        'alerta_vencimento' => 'warning',
                        'vencido' => 'danger',
                        'em_renovacao' => 'info',
                        'revogado' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('responsavel_renovacao')
                    ->label('Responsável')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('data_validade', 'asc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Situação')
                    ->options(DocumentoRegulatorio::STATUS),

                SelectFilter::make('tipo_documento')
                    ->label('Tipo de Documento')
                    ->options(DocumentoRegulatorio::TIPOS),

                SelectFilter::make('unidade_id')
                    ->label('Unidade Escolar')
                    ->relationship('unidade', 'nome'),
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

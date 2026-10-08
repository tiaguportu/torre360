<?php

namespace App\Filament\Resources\ConsentimentoMatriculas\Tables;

use App\Enums\StatusConsentimento;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ConsentimentoMatriculasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('matricula.pessoa.nome')
                    ->label('Aluno')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tipoConsentimento.nome')
                    ->label('Tipo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($record) => $record->statusEfetivo()->getLabel())
                    ->color(fn ($record) => $record->statusEfetivo()->getColor()),
                TextColumn::make('respondido_em')
                    ->label('Respondido em')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('vigencia_fim')
                    ->label('Vigência até')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('tipo_consentimento_id')
                    ->label('Tipo')
                    ->relationship('tipoConsentimento', 'nome'),
                SelectFilter::make('status')
                    ->label('Status (registrado)')
                    ->options(StatusConsentimento::class),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->stackedOnMobile();
    }
}

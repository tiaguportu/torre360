<?php

namespace App\Filament\Resources\Questionarios\Tables;

use App\Filament\Resources\Questionarios\QuestionarioResource;
use App\Models\Questionario;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class QuestionariosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('titulo')
                    ->label('Título')
                    ->searchable(),
                TextColumn::make('inicio_aplicacao')
                    ->label('Início')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('fim_aplicacao')
                    ->label('Fim')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                IconColumn::make('is_anonimo')
                    ->label('Anônimo')
                    ->boolean(),
                IconColumn::make('is_ativo')
                    ->label('Ativo')
                    ->boolean(),
                TextColumn::make('respostas_count')
                    ->label('Respostas')
                    ->counts('respostas')
                    ->badge()
                    ->color('success'),
                TextColumn::make('created_at')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('responder')
                    ->label('Responder')
                    ->icon('heroicon-o-pencil-square')
                    ->color('success')
                    ->url(fn (Questionario $record): string => QuestionarioResource::getUrl('responder', ['record' => $record]))
                    ->visible(fn (Questionario $record): bool => $record->is_ativo),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

<?php

namespace App\Filament\Resources\RelatorioPreceptorias\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RelatorioPreceptoriasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('preceptoria.professor.nome')
                    ->label('Professor(a)')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('preceptoria.data')
                    ->label('Data da Preceptoria')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('preceptoria.hora_inicio')
                    ->label('Hora Início')
                    ->time('H:i'),

                TextColumn::make('preceptoria.matricula.pessoa.nome')
                    ->label('Aluno')
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('updated_at')
                    ->label('Última Edição')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([])
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

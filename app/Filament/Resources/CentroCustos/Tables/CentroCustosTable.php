<?php

namespace App\Filament\Resources\CentroCustos\Tables;

use App\Models\CentroCusto;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CentroCustosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['turma', 'curso']))
            ->columns([
                TextColumn::make('nome')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('vinculo')
                    ->label('No resultado das turmas')
                    ->state(fn (CentroCusto $record): string => match (true) {
                        (bool) $record->fora_do_resultado_das_turmas => 'Fora do resultado',
                        $record->turma !== null => 'Direto: '.$record->turma->nome,
                        $record->curso !== null => 'Direto do curso: '.$record->curso->nome,
                        default => 'Indireto (rateado)',
                    }),
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
            ])
            ->stackedOnMobile();
    }
}

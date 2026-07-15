<?php

namespace App\Filament\Resources\TemplateContratos\Tables;

use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class TemplateContratosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_padrao')
                    ->label('Padrão')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('clonar')
                        ->label('Clonar Selecionados')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->clonar())
                        ->deselectRecordsAfterCompletion()
                        ->successNotificationTitle('Templates de contratos clonados com sucesso!'),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->stackedOnMobile();
    }
}

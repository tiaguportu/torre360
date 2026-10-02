<?php

namespace App\Filament\Resources\BolsaConcedidas\Tables;

use App\Enums\StatusBolsa;
use App\Models\BolsaConcedida;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BolsaConcedidasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('matricula.pessoa.nome')
                    ->label('Aluno')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tipoBolsa.nome')
                    ->label('Tipo de Bolsa')
                    ->searchable(),
                TextColumn::make('percentual')
                    ->label('Percentual')
                    ->suffix('%')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('data_inicio')
                    ->label('Início')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('data_fim')
                    ->label('Fim')
                    ->date('d/m/Y')
                    ->placeholder('Em aberto')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(StatusBolsa::class),
            ])
            ->recordActions([
                self::aprovarAction(),
                self::recusarAction(),
                self::encerrarAction(),
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

    public static function aprovarAction(): Action
    {
        return Action::make('aprovar')
            ->label('Aprovar')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (BolsaConcedida $record): bool => $record->status === StatusBolsa::Solicitada)
            ->requiresConfirmation()
            ->modalDescription(fn (BolsaConcedida $record): string => "Aprovar bolsa de {$record->percentual}% para {$record->matricula?->pessoa?->nome}?")
            ->action(function (BolsaConcedida $record): void {
                $record->update([
                    'status' => StatusBolsa::Aprovada,
                    'aprovado_por_user_id' => auth()->id(),
                ]);

                Notification::make()->title('Bolsa aprovada com sucesso!')->success()->send();
            });
    }

    public static function recusarAction(): Action
    {
        return Action::make('recusar')
            ->label('Recusar')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (BolsaConcedida $record): bool => $record->status === StatusBolsa::Solicitada)
            ->requiresConfirmation()
            ->schema([
                Textarea::make('motivo')
                    ->label('Motivo da Recusa')
                    ->rows(2),
            ])
            ->action(function (array $data, BolsaConcedida $record): void {
                $record->update([
                    'status' => StatusBolsa::Recusada,
                    'observacao' => trim(($record->observacao ? $record->observacao."\n" : '').($data['motivo'] ?? '')),
                ]);

                Notification::make()->title('Bolsa recusada')->success()->send();
            });
    }

    public static function encerrarAction(): Action
    {
        return Action::make('encerrar')
            ->label('Encerrar')
            ->icon('heroicon-o-archive-box')
            ->color('gray')
            ->visible(fn (BolsaConcedida $record): bool => $record->status === StatusBolsa::Aprovada)
            ->requiresConfirmation()
            ->modalDescription('A bolsa deixa de valer para as próximas faturas geradas.')
            ->action(function (BolsaConcedida $record): void {
                $record->update(['status' => StatusBolsa::Encerrada, 'data_fim' => $record->data_fim ?? now()]);

                Notification::make()->title('Bolsa encerrada')->success()->send();
            });
    }
}

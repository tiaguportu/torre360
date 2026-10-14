<?php

namespace App\Filament\Resources\TransferenciasEscolares\Tables;

use App\Enums\StatusTransferencia;
use App\Enums\TipoTransferencia;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TransferenciasEscolaresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('matricula.pessoa.nome')
                    ->label('Aluno')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge(),
                TextColumn::make('escola_externa_nome')
                    ->label('Escola Externa')
                    ->searchable(),
                TextColumn::make('data')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                IconColumn::make('historico_recebido')
                    ->label('Histórico Recebido')
                    ->boolean()
                    ->visible(fn ($record) => ! $record || $record->tipo === TipoTransferencia::Entrada),
            ])
            ->filters([
                SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options(TipoTransferencia::class),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(StatusTransferencia::class),
            ])
            ->recordActions([
                Action::make('concluir_saida')
                    ->label('Concluir Saída')
                    ->icon('heroicon-o-document-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Isso emite a Declaração de Transferência e fecha a matrícula como Cancelada. Pendências financeiras não bloqueiam a emissão (exigência legal).')
                    ->visible(fn ($record) => $record->tipo === TipoTransferencia::Saida && $record->status === StatusTransferencia::EmAndamento)
                    ->action(function ($record): void {
                        try {
                            $record->concluirSaida();

                            Notification::make()
                                ->title('Transferência concluída e declaração emitida.')
                                ->success()
                                ->send();
                        } catch (\DomainException $e) {
                            Notification::make()
                                ->title('Não foi possível concluir')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('marcar_historico_recebido')
                    ->label('Marcar Histórico Recebido')
                    ->icon('heroicon-o-inbox-arrow-down')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Confirme só depois de lançar o(s) ano(s) externo(s) no Histórico Escolar do aluno (Secretaria → Histórico Escolar).')
                    ->schema([
                        Textarea::make('observacoes')
                            ->label('Observações (opcional)')
                            ->rows(2),
                    ])
                    ->visible(fn ($record) => $record->tipo === TipoTransferencia::Entrada && ! $record->historico_recebido)
                    ->action(function ($record, array $data): void {
                        if (! empty($data['observacoes'])) {
                            $record->update(['observacoes' => $data['observacoes']]);
                        }

                        $record->marcarHistoricoRecebido();

                        Notification::make()
                            ->title('Histórico marcado como recebido.')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('data', 'desc')
            ->stackedOnMobile();
    }
}

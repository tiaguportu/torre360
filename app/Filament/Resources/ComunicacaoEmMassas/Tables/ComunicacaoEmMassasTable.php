<?php

namespace App\Filament\Resources\ComunicacaoEmMassas\Tables;

use App\Enums\StatusComunicacaoEmMassa;
use App\Enums\TipoPublicoComunicacao;
use App\Jobs\EnviarComunicacaoEmMassaJob;
use App\Models\ComunicacaoEmMassa;
use App\Services\ComunicacaoEmMassaService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ComunicacaoEmMassasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('nome')
                    ->label('Nome')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('tipo_publico')
                    ->label('Público')
                    ->badge(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('destinatarios')
                    ->label('Destinatários')
                    ->state(fn (?ComunicacaoEmMassa $record): int => $record && $record->status === StatusComunicacaoEmMassa::Rascunho
                        ? app(ComunicacaoEmMassaService::class)->destinatarios($record)->count()
                        : (int) $record?->total_destinatarios)
                    ->badge()
                    ->color('info'),
                TextColumn::make('total_enviados')
                    ->label('Enviados')
                    ->badge()
                    ->color('success')
                    ->visible(fn (?ComunicacaoEmMassa $record): bool => $record !== null && $record->status !== StatusComunicacaoEmMassa::Rascunho),
                TextColumn::make('total_falhas')
                    ->label('Falhas')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray')
                    ->visible(fn (?ComunicacaoEmMassa $record): bool => $record !== null && $record->status !== StatusComunicacaoEmMassa::Rascunho),
                TextColumn::make('enviado_em')
                    ->label('Enviado em')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('enviadoPor.name')
                    ->label('Criado por')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(StatusComunicacaoEmMassa::class),
                SelectFilter::make('tipo_publico')
                    ->label('Público')
                    ->options(TipoPublicoComunicacao::class),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (ComunicacaoEmMassa $record): bool => $record->podeSerEnviada()),
                Action::make('enviar')
                    ->label('Enviar')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn (ComunicacaoEmMassa $record): bool => $record->podeSerEnviada() && auth()->user()?->can('enviar', $record))
                    ->requiresConfirmation()
                    ->modalHeading('Enviar comunicação em massa')
                    ->modalDescription(function (ComunicacaoEmMassa $record): string {
                        $total = app(ComunicacaoEmMassaService::class)->destinatarios($record)->count();

                        return $total > 0
                            ? "Esta comunicação será enviada por e-mail para {$total} pessoa(s). Esta ação não pode ser desfeita."
                            : 'Nenhum destinatário encontrado para os filtros escolhidos. Revise os filtros antes de enviar.';
                    })
                    ->disabled(fn (ComunicacaoEmMassa $record): bool => app(ComunicacaoEmMassaService::class)->destinatarios($record)->isEmpty())
                    ->action(function (ComunicacaoEmMassa $record) {
                        EnviarComunicacaoEmMassaJob::dispatch($record);

                        Notification::make()
                            ->title('Envio iniciado')
                            ->body('A comunicação foi colocada na fila de envio.')
                            ->success()
                            ->send();
                    }),
                DeleteAction::make()
                    ->visible(fn (ComunicacaoEmMassa $record): bool => $record->podeSerEnviada()),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->stackedOnMobile();
    }
}

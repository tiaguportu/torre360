<?php

namespace App\Filament\Resources\LandingLeads\Tables;

use App\Models\LandingLead;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LandingLeadsTable
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
                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->copyable()
                    ->icon('heroicon-o-envelope'),
                TextColumn::make('whatsapp')
                    ->label('WhatsApp')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—')
                    ->icon('heroicon-o-phone'),
                TextColumn::make('mensagem')
                    ->label('Mensagem')
                    ->limit(60)
                    ->tooltip(fn (LandingLead $record): ?string => $record->mensagem)
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => LandingLead::STATUSES[$state] ?? ($state ?? '—'))
                    ->color(fn (?string $state): string => match ($state) {
                        LandingLead::STATUS_NOVO => 'warning',
                        LandingLead::STATUS_EM_CONTATO => 'info',
                        LandingLead::STATUS_DESCARTADO => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Recebido em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(LandingLead::STATUSES),
            ])
            ->actions([
                Action::make('emContato')
                    ->label('Em contato')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('info')
                    ->visible(fn (LandingLead $record): bool => $record->status === LandingLead::STATUS_NOVO)
                    ->action(fn (LandingLead $record) => self::alterarStatus($record, LandingLead::STATUS_EM_CONTATO)),
                Action::make('descartar')
                    ->label('Descartar')
                    ->icon('heroicon-o-x-circle')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (LandingLead $record): bool => $record->status !== LandingLead::STATUS_DESCARTADO)
                    ->action(fn (LandingLead $record) => self::alterarStatus($record, LandingLead::STATUS_DESCARTADO)),
                Action::make('reabrir')
                    ->label('Reabrir')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (LandingLead $record): bool => $record->status === LandingLead::STATUS_DESCARTADO)
                    ->action(fn (LandingLead $record) => self::alterarStatus($record, LandingLead::STATUS_NOVO)),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Nenhum lead recebido pela landing page')
            ->stackedOnMobile();
    }

    private static function alterarStatus(LandingLead $lead, string $status): void
    {
        $lead->update(['status' => $status]);

        Notification::make()
            ->title('Status atualizado para "'.LandingLead::STATUSES[$status].'".')
            ->success()
            ->send();
    }
}

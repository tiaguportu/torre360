<?php

namespace App\Filament\Resources\ReguaFollowUps\Tables;

use App\Enums\CanalReguaFollowUp;
use App\Enums\GatilhoReguaFollowUp;
use App\Models\Interessado;
use App\Models\ReguaFollowUp;
use App\Services\ReguaFollowUpService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ReguaFollowUpsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')
                    ->label('Nome da Automação')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('gatilho')
                    ->label('Gatilho')
                    ->badge(),

                TextColumn::make('dias_offset')
                    ->label('Momento do Disparo')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (ReguaFollowUp $record): string => $record->gatilho_descricao)
                    ->sortable(),

                TextColumn::make('canal')
                    ->label('Canal')
                    ->badge(),

                IconColumn::make('is_ativo')
                    ->label('Ativa')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('logs_count')
                    ->label('Disparos')
                    ->counts('logs')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                TextColumn::make('horario_envio')
                    ->label('Horário')
                    ->time('H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_ativo')
                    ->label('Somente Ativas'),

                SelectFilter::make('gatilho')
                    ->label('Gatilho')
                    ->options(GatilhoReguaFollowUp::class),

                SelectFilter::make('canal')
                    ->label('Canal')
                    ->options(CanalReguaFollowUp::class),
            ])
            ->recordActions([
                EditAction::make(),

                Action::make('testar')
                    ->label('Testar')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->modalHeading('Testar Disparo desta Automação')
                    ->modalDescription('Selecione um lead para enviar uma notificação de teste utilizando os dados reais.')
                    ->schema([
                        Select::make('interessado_id')
                            ->label('Lead de Teste')
                            ->options(fn () => Interessado::with('pessoa')
                                ->limit(40)
                                ->get()
                                ->mapWithKeys(fn (Interessado $i) => [
                                    $i->id => "{$i->pessoa?->nome} (Email: {$i->pessoa?->email})",
                                ]))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (array $data, ReguaFollowUp $record): void {
                        $interessado = Interessado::find($data['interessado_id']);

                        if (! $interessado) {
                            Notification::make()
                                ->title('Lead não encontrado.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $res = app(ReguaFollowUpService::class)->enviarNotificacao($record, $interessado, $interessado->proximaVisita);

                        if ($res['sucesso']) {
                            Notification::make()
                                ->title('Disparo de teste realizado com sucesso!')
                                ->body($res['mensagem'])
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Falha no disparo de teste')
                                ->body($res['mensagem'])
                                ->warning()
                                ->send();
                        }
                    })
                    ->visible(fn () => auth()->user()?->can('Execute:ReguaFollowUp') ?? false),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->stackedOnMobile();
    }
}

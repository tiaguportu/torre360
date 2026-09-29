<?php

namespace App\Filament\Resources\ReguaCobrancas\Tables;

use App\Models\Fatura;
use App\Models\ReguaCobranca;
use App\Services\ReguaCobrancaService;
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

class ReguaCobrancasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')
                    ->label('Nome da Régua')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('dias_offset')
                    ->label('Momento do Gatilho')
                    ->badge()
                    ->color(fn (ReguaCobranca $record): string => $record->gatilho_badge_color)
                    ->formatStateUsing(fn (ReguaCobranca $record): string => $record->gatilho_descricao)
                    ->sortable(),

                TextColumn::make('canal')
                    ->label('Canal')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'email' => 'E-mail',
                        'portal' => 'Portal',
                        'push' => 'Push',
                        default => 'Todos (Multicanal)',
                    }),

                IconColumn::make('is_ativo')
                    ->label('Ativo')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('logs_count')
                    ->label('Disparos Efetuados')
                    ->counts('logs')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('horario_envio')
                    ->label('Horário')
                    ->time('H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_ativo')
                    ->label('Somente Ativas'),

                SelectFilter::make('tipo_gatilho')
                    ->label('Momento')
                    ->options([
                        'antes_vencimento' => 'Antes do Vencimento',
                        'no_vencimento' => 'No Dia do Vencimento',
                        'apos_vencimento' => 'Após o Vencimento',
                    ]),

                SelectFilter::make('canal')
                    ->label('Canal')
                    ->options([
                        'todos' => 'Todos os Canais',
                        'email' => 'E-mail',
                        'portal' => 'Portal',
                        'push' => 'Push',
                    ]),
            ])
            ->recordActions([
                Action::make('testar')
                    ->label('Testar')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->modalHeading('Testar Disparo desta Régua')
                    ->modalDescription('Selecione uma fatura em aberto para enviar uma mensagem de teste usando os dados reais.')
                    ->schema([
                        Select::make('fatura_id')
                            ->label('Fatura de Teste')
                            ->options(fn () => Fatura::whereIn('status', ['pendente', 'atrasado', 'parcial'])
                                ->with('contrato.matricula.pessoa')
                                ->limit(30)
                                ->get()
                                ->mapWithKeys(fn (Fatura $f) => [
                                    $f->id => "#{$f->id} - Aluno: ".($f->contrato?->matricula?->pessoa?->nome ?? 'N/I')." (Venc: {$f->vencimento?->format('d/m/Y')})",
                                ])
                            )
                            ->required()
                            ->searchable(),
                    ])
                    ->action(function (array $data, ReguaCobranca $record): void {
                        $fatura = Fatura::findOrFail($data['fatura_id']);

                        try {
                            $res = app(ReguaCobrancaService::class)->dispararLembreteManual(
                                $fatura,
                                $record,
                                null,
                                null,
                                $record->canal
                            );

                            Notification::make()
                                ->title('Disparo de teste realizado!')
                                ->body("{$res['total_enviados']} notificação(ões) enviada(s) com sucesso.")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Erro ao testar régua')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('ordem', 'asc')
            ->stackedOnMobile();
    }
}

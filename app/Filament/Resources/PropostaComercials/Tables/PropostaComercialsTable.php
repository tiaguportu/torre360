<?php

namespace App\Filament\Resources\PropostaComercials\Tables;

use App\Enums\NivelAlcadaComercial;
use App\Enums\StatusPropostaComercial;
use App\Models\PropostaComercial;
use App\Models\User;
use App\Services\RevenueManagementService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PropostaComercialsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('responsavel_nome')
                    ->label('Responsável / Aluno')
                    ->description(fn (PropostaComercial $record) => $record->aluno_nome ? "Aluno: {$record->aluno_nome}" : ($record->responsavel_telefone ?? '—'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('serie.nome')
                    ->label('Segmento')
                    ->description(fn (PropostaComercial $record) => ($record->curso?->nome ?? '').' • '.($record->unidade?->nome ?? ''))
                    ->sortable(),

                TextColumn::make('valor_tabela_mensal')
                    ->label('Tabela')
                    ->money('BRL')
                    ->sortable(),

                TextColumn::make('desconto_solicitado')
                    ->label('Desconto')
                    ->state(fn (PropostaComercial $record) => $record->tipo_desconto === 'percentual'
                        ? number_format((float) $record->desconto_solicitado, 1, ',', '.').'%'
                        : 'R$ '.number_format((float) $record->desconto_solicitado, 2, ',', '.'))
                    ->badge()
                    ->color('warning'),

                TextColumn::make('valor_liquido_mensal')
                    ->label('Líquido Mensal')
                    ->money('BRL')
                    ->weight('bold')
                    ->color('success')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Situação')
                    ->badge()
                    ->sortable(),

                TextColumn::make('nivel_alcada_necessario')
                    ->label('Alçada')
                    ->badge()
                    ->sortable(),

                TextColumn::make('solicitante.name')
                    ->label('Consultor')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('validade')
                    ->label('Validade')
                    ->date('d/m/Y')
                    ->color(fn (PropostaComercial $record) => $record->validade && $record->validade->isPast() ? 'danger' : 'gray')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Situação')
                    ->options(StatusPropostaComercial::class),

                SelectFilter::make('nivel_alcada_necessario')
                    ->label('Alçada')
                    ->options(NivelAlcadaComercial::class),

                SelectFilter::make('solicitado_por_user_id')
                    ->label('Consultor Responsável')
                    ->options(fn () => User::pluck('name', 'id')->toArray()),
            ])
            ->recordActions([
                Action::make('aprovar')
                    ->label('Aprovar')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (PropostaComercial $record) => "Aprovar Proposta {$record->codigo}")
                    ->modalDescription('Você confirma a concessão da condição comercial solicitada pela família?')
                    ->visible(fn (PropostaComercial $record) => $record->isPendente() && $record->podeSerAprovadaPor(auth()->user()))
                    ->action(function (PropostaComercial $record): void {
                        app(RevenueManagementService::class)->aprovar($record, auth()->user());
                        Notification::make()
                            ->title('Proposta Comercial Aprovada!')
                            ->success()
                            ->send();
                    }),

                Action::make('recusar')
                    ->label('Recusar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (PropostaComercial $record) => $record->isPendente() && $record->podeSerAprovadaPor(auth()->user()))
                    ->form([
                        Textarea::make('motivo')
                            ->label('Motivo da Recusa / Contraproposta')
                            ->required()
                            ->placeholder('Informe a justificativa que será enviada ao consultor comercial.'),
                    ])
                    ->action(function (PropostaComercial $record, array $data): void {
                        app(RevenueManagementService::class)->recusar($record, auth()->user(), $data['motivo']);
                        Notification::make()
                            ->title('Proposta Recusada')
                            ->danger()
                            ->send();
                    }),

                Action::make('whatsapp')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('success')
                    ->url(function (PropostaComercial $record): string {
                        $texto = app(RevenueManagementService::class)->gerarTextoWhatsapp($record);
                        $telefoneLimpo = preg_replace('/\D/', '', (string) $record->responsavel_telefone);
                        $destinatario = $telefoneLimpo ? "55{$telefoneLimpo}" : '';

                        return "https://api.whatsapp.com/send?phone={$destinatario}&text=".urlencode($texto);
                    }, shouldOpenInNewTab: true),

                Action::make('espelho')
                    ->label('Ver Proposta')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->color('gray')
                    ->modalHeading(fn (PropostaComercial $record) => "Espelho da Proposta {$record->codigo}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->modalContent(function (PropostaComercial $record) {
                        return view('filament.components.proposta-comercial-modal', [
                            'proposta' => $record,
                        ]);
                    }),

                Action::make('converter_matricula')
                    ->label('Matricular')
                    ->icon('heroicon-o-academic-cap')
                    ->color('primary')
                    ->visible(fn (PropostaComercial $record) => in_array($record->status, [StatusPropostaComercial::Aprovada, StatusPropostaComercial::AprovadaAutomatica, StatusPropostaComercial::AceitaPelaFamilia]))
                    ->url(fn (PropostaComercial $record) => url("/admin/enrollment-wizard?proposta_id={$record->id}")),

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
}

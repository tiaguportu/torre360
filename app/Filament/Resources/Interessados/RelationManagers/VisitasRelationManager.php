<?php

namespace App\Filament\Resources\Interessados\RelationManagers;

use App\Enums\StatusVisitaInteressado;
use App\Models\User;
use App\Models\VisitaInteressado;
use App\Services\LeadScoreService;
use App\Services\VisitaInteressadoService;
use App\Support\PermissaoAcao;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class VisitasRelationManager extends RelationManager
{
    protected static string $relationship = 'visitas';

    protected static ?string $title = 'Visitas à Escola';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DateTimePicker::make('data_hora')
                    ->label('Data e Hora')
                    ->seconds(false)
                    ->native(false)
                    ->displayFormat('d/m/Y H:i')
                    ->required(),
                Select::make('usuario_id')
                    ->label('Consultor')
                    ->options(fn () => User::orderBy('name')->pluck('name', 'id'))
                    ->searchable(),
                Select::make('interessado_dependente_id')
                    ->label('Aluno')
                    ->options(fn () => $this->getOwnerRecord()->dependentes()->pluck('nome_crianca', 'id'))
                    ->placeholder('Toda a família'),
                Select::make('status')
                    ->label('Situação')
                    ->options(StatusVisitaInteressado::class)
                    ->default(StatusVisitaInteressado::Agendada)
                    ->required(),
                Textarea::make('observacoes')
                    ->label('Observações')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('data_hora')
            ->defaultSort('data_hora', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['pesquisa', 'dependente', 'usuario']))
            ->columns([
                TextColumn::make('data_hora')
                    ->label('Data e Hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->color(fn (VisitaInteressado $record): ?string => $record->estaAtrasada() ? 'danger' : null),
                TextColumn::make('status')
                    ->label('Situação')
                    ->badge(),
                TextColumn::make('pesquisa.nota_nps')
                    ->label('NPS')
                    ->state(function (VisitaInteressado $record): string {
                        $pesquisa = $record->pesquisa;
                        if (! $pesquisa || ! $pesquisa->isRespondida()) {
                            return $record->status === StatusVisitaInteressado::Realizada ? 'Pendente' : '—';
                        }

                        return "{$pesquisa->nota_nps}/10 ({$pesquisa->classificacaoNps()})";
                    })
                    ->badge()
                    ->color(function (VisitaInteressado $record): string {
                        $pesquisa = $record->pesquisa;
                        if (! $pesquisa || ! $pesquisa->isRespondida()) {
                            return 'gray';
                        }

                        return $pesquisa->corBadge();
                    })
                    ->icon(function (VisitaInteressado $record): ?string {
                        $pesquisa = $record->pesquisa;
                        if (! $pesquisa || ! $pesquisa->isRespondida()) {
                            return null;
                        }

                        return $pesquisa->iconeBadge();
                    }),
                TextColumn::make('usuario.name')
                    ->label('Consultor')
                    ->placeholder('—')
                    ->icon('heroicon-o-user'),
                TextColumn::make('dependente.nome_crianca')
                    ->label('Aluno')
                    ->placeholder('Toda a família'),
                TextColumn::make('observacoes')
                    ->label('Observações')
                    ->limit(50)
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Situação')
                    ->options(StatusVisitaInteressado::class),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Agendar Visita')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['usuario_id'] ??= auth()->id();

                        return $data;
                    })
                    ->after(fn () => $this->sincronizarProximoContato()),
            ])
            ->actions([
                Action::make('realizada')
                    ->label('Realizada')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (VisitaInteressado $record): bool => $record->status === StatusVisitaInteressado::Agendada)
                    // Ações personalizadas não herdam a policy: mudar o status da visita recalcula o score e gera a pesquisa.
                    ->authorize(PermissaoAcao::qualquer('Update:VisitaInteressado'))
                    ->action(fn (VisitaInteressado $record) => $this->alterarStatus($record, StatusVisitaInteressado::Realizada)),
                Action::make('faltou')
                    ->label('Não compareceu')
                    ->icon('heroicon-o-user-minus')
                    ->color('danger')
                    ->visible(fn (VisitaInteressado $record): bool => $record->status === StatusVisitaInteressado::Agendada)
                    ->authorize(PermissaoAcao::qualquer('Update:VisitaInteressado'))
                    ->action(fn (VisitaInteressado $record) => $this->alterarStatus($record, StatusVisitaInteressado::Faltou)),
                Action::make('enviarPesquisaWhatsapp')
                    ->label('Pesquisa WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('success')
                    ->tooltip('Enviar pesquisa de satisfação pós-tour para a família pelo WhatsApp')
                    ->visible(fn (VisitaInteressado $record): bool => $record->status === StatusVisitaInteressado::Realizada)
                    // A URL cria a pesquisa (obterOuCriarPesquisa) ao renderizar a tabela; sem autorização, só ver bastava para gravar.
                    ->authorize(PermissaoAcao::qualquer('Update:VisitaInteressado'))
                    ->url(function (VisitaInteressado $record): ?string {
                        $pesquisa = $record->obterOuCriarPesquisa();

                        return $pesquisa->linkWhatsapp();
                    })
                    ->openUrlInNewTab(),
                Action::make('verAvaliacao')
                    ->label('Ver Avaliação')
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->tooltip('Visualizar avaliação e depoimento da família')
                    ->visible(fn (VisitaInteressado $record): bool => (bool) $record->pesquisa?->isRespondida())
                    ->modalHeading('Avaliação da Família - Tour Escolar')
                    ->modalWidth(Width::Large)
                    ->modalContent(fn (VisitaInteressado $record) => view(
                        'filament.crm.modal-avaliacao-pesquisa',
                        ['pesquisa' => $record->pesquisa]
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar'),
                EditAction::make()
                    ->after(fn () => $this->sincronizarProximoContato()),
                DeleteAction::make()
                    ->after(fn () => $this->sincronizarProximoContato()),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->stackedOnMobile();
    }

    private function alterarStatus(VisitaInteressado $visita, StatusVisitaInteressado $status): void
    {
        $visita->update(['status' => $status]);

        LeadScoreService::recalcular($this->getOwnerRecord());

        if ($status === StatusVisitaInteressado::Realizada) {
            $pesquisa = $visita->obterOuCriarPesquisa();
            $linkWhatsapp = $pesquisa->linkWhatsapp();

            $notification = Notification::make()
                ->title('Visita marcada como Realizada!')
                ->body('A pesquisa de satisfação pós-tour (NPS) foi gerada. Deseja enviar o convite para a família agora?')
                ->success();

            if ($linkWhatsapp) {
                $notification->actions([
                    Action::make('whatsapp')
                        ->label('Enviar pelo WhatsApp')
                        ->icon('heroicon-o-chat-bubble-left-ellipsis')
                        ->color('success')
                        ->url($linkWhatsapp, shouldOpenInNewTab: true),
                ]);
            }

            $notification->send();
        }
    }

    /**
     * Mantém "Próximo Contato" do lead alinhado à próxima visita agendada.
     */
    private function sincronizarProximoContato(): void
    {
        VisitaInteressadoService::sincronizarProximoContato($this->getOwnerRecord());
        LeadScoreService::recalcular($this->getOwnerRecord());
    }
}

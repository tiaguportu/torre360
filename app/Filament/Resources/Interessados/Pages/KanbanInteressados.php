<?php

namespace App\Filament\Resources\Interessados\Pages;

use App\Filament\Resources\Interessados\Actions\ImportarLeadIaAction;
use App\Filament\Resources\Interessados\InteressadoResource;
use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Models\User;
use App\Models\VideoTutorial;
use App\Services\LeadScoreService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Collection;

class KanbanInteressados extends Page
{
    protected static string $resource = InteressadoResource::class;

    protected string $view = 'filament.resources.interessados.pages.kanban-interessados';

    protected static ?string $title = 'Funil de Vendas (CRM)';

    protected static ?string $slug = 'kanban';

    public ?int $filtroConsultorId = null;

    /** Propriedades de controle do Modal Obrigatório de Motivo de Perda (Stage Gate) */
    public bool $modalPerdaAberto = false;

    public ?int $leadPerdaId = null;

    public ?int $statusPerdaId = null;

    public ?string $leadPerdaNome = null;

    public ?string $statusPerdaNome = null;

    public string $motivoPerda = '';

    public ?string $concorrentePerda = null;

    public ?string $observacoesPerda = null;

    protected function getHeaderActions(): array
    {
        return [
            ImportarLeadIaAction::make(),
            Action::make('filtroConsultor')
                ->label('Filtrar Consultor')
                ->icon('heroicon-o-funnel')
                ->color('gray')
                ->form([
                    Select::make('consultor_id')
                        ->label('Consultor')
                        ->options(User::pluck('name', 'id'))
                        ->searchable()
                        ->placeholder('Todos os consultores'),
                ])
                ->action(function (array $data) {
                    $this->filtroConsultorId = $data['consultor_id'] ?? null;
                }),
            Action::make('termometroVagas')
                ->label('Termômetro de Vagas')
                ->icon('heroicon-o-chart-bar')
                ->color('warning')
                ->modalHeading('📊 Termômetro de Ocupação e Vagas por Série')
                ->modalWidth(Width::Large)
                ->modalContent(fn () => view('filament.crm.modal-termometro-vagas'))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar'),
            Action::make('list')
                ->label('Ver Lista')
                ->icon('heroicon-o-list-bullet')
                ->color('info')
                ->url(InteressadoResource::getUrl('index')),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Funil de Vendas (Kanban)')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                            'video' => VideoTutorial::query()->ativo()->where('chave_pagina', 'interessados-kanban')->orderBy('ordem')->first(),
                        ]),
                ]),
        ];
    }

    public function getStatuses(): Collection
    {
        return StatusInteressado::orderBy('ordem')->get();
    }

    public function getInteressados(): Collection
    {
        $query = Interessado::with(['pessoa', 'status', 'origem', 'usuario', 'dependentes.serie', 'ultimoHistorico', 'visitas.pesquisa', 'indicacao.quemIndicou', 'documentosInseridos']);

        if ($this->filtroConsultorId) {
            $query->where('usuario_id', $this->filtroConsultorId);
        }

        return $query->get();
    }

    public function updateRecordStatus($recordId, $statusId): void
    {
        $record = Interessado::with(['pessoa', 'status'])->find($recordId);
        $novoStatus = StatusInteressado::find($statusId);

        if (! $record || ! $novoStatus) {
            return;
        }

        // Se o novo status é de perda, intercepta e abre o modal obrigatório (Stage Gate)
        if ($novoStatus->isPerda()) {
            $this->abrirModalPerda($record, $novoStatus);

            return;
        }

        $updateData = ['status_interessado_id' => $statusId];

        // Se o status anterior era de perda e agora foi reativado para um status ativo
        if ($record->status?->isPerda()) {
            $updateData['motivo_perda'] = null;

            $tipoContatoId = TipoContatoInteressado::where('nome', 'like', '%Presencial%')->value('id') ?? 1;
            HistoricoContato::create([
                'interessado_id' => $record->id,
                'tipo_contato_interessado_id' => $tipoContatoId,
                'data_contato' => now(),
                'usuario_id' => auth()->id(),
                'relato' => "Lead reativado no Funil de Vendas: movido de '{$record->status->nome}' para '{$novoStatus->nome}'.",
                'resultado' => 'retornar',
            ]);
        }

        // Se moveu para status de ganho, registra data de conversão
        if ($novoStatus->is_ganho && ! $record->data_conversao) {
            $updateData['data_conversao'] = now();
        }

        $record->update($updateData);

        LeadScoreService::recalcular($record);

        Notification::make()
            ->title('Status atualizado!')
            ->success()
            ->send();
    }

    /**
     * Abre e inicializa o modal de motivo de perda para o lead selecionado.
     */
    public function abrirModalPerda(Interessado $record, StatusInteressado $novoStatus): void
    {
        $this->leadPerdaId = $record->id;
        $this->statusPerdaId = $novoStatus->id;
        $this->leadPerdaNome = $record->pessoa?->nome ?? 'Interessado';
        $this->statusPerdaNome = $novoStatus->nome;
        $this->motivoPerda = $record->motivo_perda ?? '';
        $this->concorrentePerda = null;
        $this->observacoesPerda = null;
        $this->modalPerdaAberto = true;

        $this->dispatch('open-modal', id: 'modal-motivo-perda');
    }

    /**
     * Valida e confirma o motivo de perda, movendo o lead e auditando no histórico.
     */
    public function confirmarPerda(): void
    {
        $this->validate([
            'motivoPerda' => ['required', 'string'],
            'concorrentePerda' => ['nullable', 'string', 'max:255'],
            'observacoesPerda' => ['nullable', 'string', 'max:1000'],
        ], [
            'motivoPerda.required' => 'O motivo da perda é obrigatório para registrar o descarte do lead.',
        ]);

        $record = Interessado::find($this->leadPerdaId);
        $novoStatus = StatusInteressado::find($this->statusPerdaId);

        if (! $record || ! $novoStatus) {
            $this->fecharModalPerda();

            return;
        }

        // Monta o texto do motivo da perda
        $motivoFinal = $this->motivoPerda;
        if ($this->motivoPerda === 'Concorrência' && filled($this->concorrentePerda)) {
            $motivoFinal .= ': '.trim($this->concorrentePerda);
        }

        $relatoHistorico = "Lead marcado como perdido no Funil de Vendas ({$novoStatus->nome}). Motivo: {$motivoFinal}.";
        if (filled($this->observacoesPerda)) {
            $relatoHistorico .= ' Detalhes: '.trim($this->observacoesPerda);
        }

        $record->update([
            'status_interessado_id' => $novoStatus->id,
            'motivo_perda' => $motivoFinal,
        ]);

        // Registra histórico de atendimento para auditoria e relatórios de perdas
        $tipoContatoId = TipoContatoInteressado::where('nome', 'like', '%Presencial%')->value('id') ?? 1;
        HistoricoContato::create([
            'interessado_id' => $record->id,
            'tipo_contato_interessado_id' => $tipoContatoId,
            'data_contato' => now(),
            'usuario_id' => auth()->id(),
            'relato' => $relatoHistorico,
            'resultado' => 'sem_interesse',
        ]);

        LeadScoreService::recalcular($record);

        $this->fecharModalPerda();

        Notification::make()
            ->title('Lead marcado como perdido!')
            ->body("O motivo \"{$motivoFinal}\" foi registrado no histórico do interessado.")
            ->warning()
            ->send();
    }

    /**
     * Fecha o modal de perda e limpa os campos de formulário temporários.
     */
    public function fecharModalPerda(): void
    {
        $this->modalPerdaAberto = false;
        $this->leadPerdaId = null;
        $this->statusPerdaId = null;
        $this->leadPerdaNome = null;
        $this->statusPerdaNome = null;
        $this->motivoPerda = '';
        $this->concorrentePerda = null;
        $this->observacoesPerda = null;
        $this->resetValidation();

        $this->dispatch('close-modal', id: 'modal-motivo-perda');
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();
        $canCreate = $user->can('Create:Interessado');
        $canUpdate = $user->can('Update:Interessado');

        $html = '<p>O <strong>Funil de Vendas</strong> (Kanban) permite visualizar e gerenciar seus leads de forma visual, arrastando os cards entre as etapas do processo comercial.</p>';
        $html .= '<h3>Como usar:</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>📊 Termômetro de Vagas:</strong> Consulte o botão no topo para ver a ocupação real de cada série e turma em tempo real.</li>';
        $html .= '<li><strong>🔥 Alertas de Escassez nos Cards:</strong> As séries pretendidas nos cards mostram alertas dinâmicos de vagas restantes (ex: <em>Esgotado</em>, <em>Últimas vagas</em>, <em>Vagas limitadas</em>).</li>';
        $html .= '<li><strong>Visualização:</strong> Cada coluna representa um status do funil. Os cards mostram o interessado, origem, dependentes e próximo contato.</li>';
        $html .= '<li><strong>Arrastar e Soltar:</strong> Mova os cards entre colunas para atualizar o status do lead.</li>';
        $html .= '<li><strong>🛑 Motivo de Perda Obrigatório (Stage Gate):</strong> Ao arrastar um lead para uma coluna de encerramento/perda (ex: <em>Desistente</em>, <em>Perdido</em>), o sistema abre obrigatoriamente um modal para registro da razão da perda, concorrente e anotações, qualificando a inteligência comercial da instituição.</li>';
        $html .= '<li><strong>Cards em Vermelho:</strong> Indicam leads com contato atrasado (urgente!).</li>';
        $html .= '<li><strong>Filtro de Consultor:</strong> Use o botão "Filtrar Consultor" para ver apenas os leads de um consultor específico.</li>';

        if ($canUpdate) {
            $html .= '<li><strong>Editar:</strong> Clique no ícone de lápis para abrir os detalhes do lead.</li>';
        }

        $html .= '</ul>';

        return $html;
    }
}

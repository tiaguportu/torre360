<?php

namespace App\Filament\Resources\Interessados\Pages;

use App\Filament\Pages\EnrollmentWizard;
use App\Filament\Resources\Interessados\Actions\BattlecardAction;
use App\Filament\Resources\Interessados\Actions\ImportarLeadIaAction;
use App\Filament\Resources\Interessados\InteressadoResource;
use App\Models\Concorrente;
use App\Models\Interessado;
use App\Models\StatusInteressado;
use App\Models\User;
use App\Models\VideoTutorial;
use App\Services\LeadFunilService;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

class KanbanInteressados extends Page
{
    protected static string $resource = InteressadoResource::class;

    protected string $view = 'filament.resources.interessados.pages.kanban-interessados';

    protected static ?string $title = 'Funil de Vendas (CRM)';

    protected static ?string $slug = 'kanban';

    public ?int $filtroConsultorId = null;

    /**
     * Quantos cards cada coluna já pediu (id da etapa => limite), para o botão "Carregar mais".
     *
     * @var array<int, int>
     */
    public array $limitesPorColuna = [];

    /**
     * Propriedades de controle do Modal Obrigatório de Motivo de Perda (Stage Gate).
     *
     * O lead e o status em jogo são `#[Locked]`: só o servidor os define (em `abrirModalPerda`, depois de
     * autorizar). Sem isso, o cliente poderia trocar os ids antes de `confirmarPerda` e alterar qualquer lead.
     */
    #[Locked]
    public bool $modalPerdaAberto = false;

    #[Locked]
    public ?int $leadPerdaId = null;

    #[Locked]
    public ?int $statusPerdaId = null;

    #[Locked]
    public ?string $leadPerdaNome = null;

    #[Locked]
    public ?string $statusPerdaNome = null;

    public string $motivoPerda = '';

    public ?int $concorrenteId = null;

    public ?string $concorrentePerda = null;

    public ?string $fatorDecisivoConcorrente = null;

    public ?string $observacoesPerda = null;

    protected function getHeaderActions(): array
    {
        return [
            BattlecardAction::make(),
            ImportarLeadIaAction::make(),
            Action::make('filtroConsultor')
                ->label('Filtrar Consultor')
                ->icon('heroicon-o-funnel')
                ->color('gray')
                ->form([
                    Select::make('consultor_id')
                        ->label('Consultor')
                        ->options(fn () => User::consultoresCrm()->orderBy('name')->pluck('name', 'id'))
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

    /**
     * Colunas do funil com os cards visíveis. Antes a view chamava `getInteressados()` dentro do laço de
     * colunas — uma consulta com todos os leads e 9 relações por coluna, a cada render, incluindo
     * matriculados e perdidos de anos atrás. Agora:
     *
     *  1. um agregado traz total e valor de todas as colunas (uma consulta);
     *  2. cada coluna busca só os ids dos primeiros N cards, na ordem de urgência (consulta leve e limitada);
     *  3. uma única consulta carrega esses leads com as relações que o card usa.
     *
     * Colunas finais (matriculado/perdido) mostram só o que foi movido nos últimos
     * `crm.kanban.dias_finalizados` dias; o restante continua na aba "Finalizados" da listagem.
     *
     * @return Collection<int, array{status: StatusInteressado, total: int, valor: float, leads: Collection<int, Interessado>, tem_mais: bool, janela_dias: ?int}>
     */
    #[Computed]
    public function colunas(): Collection
    {
        $statuses = StatusInteressado::query()->orderBy('ordem')->get();
        $passo = max(1, (int) config('crm.kanban.cards_por_coluna', 30));
        $maximo = max($passo, (int) config('crm.kanban.cards_maximo_por_coluna', 300));
        $diasFinalizados = max(1, (int) config('crm.kanban.dias_finalizados', 90));
        $corteFinal = now()->subDays($diasFinalizados);
        $idsFinais = $statuses->where('is_final', true)->pluck('id');

        $base = fn (): Builder => Interessado::query()
            ->when($this->filtroConsultorId, fn (Builder $query, int $consultorId) => $query->where('usuario_id', $consultorId));

        $totais = $base()
            ->selectRaw('status_interessado_id, count(*) as total, coalesce(sum(valor_estimado), 0) as valor')
            ->where(fn (Builder $query) => $query
                ->whereNotIn('status_interessado_id', $idsFinais)
                ->orWhere(fn (Builder $finais) => $finais->whereIn('status_interessado_id', $idsFinais)->where('updated_at', '>=', $corteFinal)))
            ->groupBy('status_interessado_id')
            ->get()
            ->keyBy('status_interessado_id');

        $idsPorColuna = $statuses->mapWithKeys(function (StatusInteressado $status) use ($base, $passo, $maximo, $corteFinal): array {
            $limite = min($maximo, max($passo, (int) ($this->limitesPorColuna[$status->id] ?? $passo)));
            $consulta = $base()->where('status_interessado_id', $status->id);

            $consulta = $status->is_final
                ? $consulta->where('updated_at', '>=', $corteFinal)->orderByDesc('updated_at')
                : $consulta->orderByRaw('data_proximo_contato is null')->orderBy('data_proximo_contato');

            return [$status->id => $consulta->orderByDesc('id')->limit($limite)->pluck('id')];
        });

        $todosIds = $idsPorColuna->flatten();

        $leads = $todosIds->isEmpty()
            ? collect()
            : Interessado::query()
                ->with(['pessoa', 'origem', 'usuario', 'dependentes.serie', 'ultimoHistorico', 'visitas.pesquisa', 'indicacao.quemIndicou'])
                ->withCount('documentosInseridos')
                ->whereIn('id', $todosIds)
                ->get()
                ->keyBy('id');

        return $statuses->map(function (StatusInteressado $status) use ($totais, $idsPorColuna, $leads, $diasFinalizados): array {
            $total = (int) ($totais[$status->id]->total ?? 0);
            $cards = $idsPorColuna[$status->id]->map(fn (int $id) => $leads[$id] ?? null)->filter()->values();

            return [
                'status' => $status,
                'total' => $total,
                'valor' => (float) ($totais[$status->id]->valor ?? 0),
                'leads' => $cards,
                'tem_mais' => $total > $cards->count(),
                'janela_dias' => $status->is_final ? $diasFinalizados : null,
            ];
        })->values();
    }

    /**
     * "Carregar mais" de uma coluna: soma um lote de cards ao limite dela, até o teto configurado.
     */
    public function carregarMais(int|string $statusId): void
    {
        $statusId = (int) $statusId;
        $passo = max(1, (int) config('crm.kanban.cards_por_coluna', 30));
        $maximo = max($passo, (int) config('crm.kanban.cards_maximo_por_coluna', 300));

        $this->limitesPorColuna[$statusId] = min($maximo, ($this->limitesPorColuna[$statusId] ?? $passo) + $passo);
    }

    /**
     * Quem pode mover cards (e, portanto, arrastá-los): mesma permissão de editar o lead.
     */
    public function podeMoverLeads(): bool
    {
        return (bool) auth()->user()?->can('Update:Interessado');
    }

    public function updateRecordStatus($recordId, $statusId): void
    {
        $record = Interessado::with(['pessoa', 'status'])->find((int) $recordId);
        $novoStatus = StatusInteressado::find((int) $statusId);

        if (! $record || ! $novoStatus) {
            return;
        }

        if (! $this->podeAlterar($record)) {
            $this->notificarSemPermissao();

            return;
        }

        // Soltar no mesmo status em que o card já está não é uma movimentação.
        if ($record->status_interessado_id === $novoStatus->id) {
            return;
        }

        // Lead matriculado só sai do estado de ganho pelo módulo de Matrículas: a matrícula continua existindo.
        if ($record->status?->is_ganho) {
            $this->notificarBloqueio('Lead já matriculado', 'Este lead já foi matriculado. Alterações são feitas no módulo de Matrículas.');

            return;
        }

        // Se o novo status é de perda, intercepta e abre o modal obrigatório (Stage Gate)
        if ($novoStatus->isPerda()) {
            $this->abrirModalPerda($record, $novoStatus);

            return;
        }

        // Arrastar para "Matriculado" não é uma matrícula: leva ao Assistente de Matrícula (que converte o
        // lead ao concluir). Quem não tem acesso ao assistente usa o mesmo atalho "Marcar matriculado" da tabela.
        if ($novoStatus->is_ganho) {
            $this->concluirMatricula($record);

            return;
        }

        try {
            app(LeadFunilService::class)->moverParaEtapaAtiva($record, $novoStatus, auth()->id());
        } catch (DomainException $e) {
            $this->notificarBloqueio('Não foi possível mover o lead', $e->getMessage());

            return;
        }

        Notification::make()
            ->title('Status atualizado!')
            ->success()
            ->send();
    }

    /**
     * Destino de um card solto numa etapa de ganho: Assistente de Matrícula pré-preenchido com o lead,
     * ou — sem acesso ao assistente — o atalho que apenas marca o lead como matriculado.
     */
    private function concluirMatricula(Interessado $record): void
    {
        if (EnrollmentWizard::canAccess()) {
            Notification::make()
                ->title('Conclua a matrícula')
                ->body('O lead só vira "Matriculado" quando a matrícula é concluída. O assistente foi aberto com os dados dele.')
                ->info()
                ->send();

            $this->redirect(EnrollmentWizard::getUrl(['interessado' => $record->id]));

            return;
        }

        try {
            app(LeadFunilService::class)->marcarMatriculado($record);
        } catch (DomainException $e) {
            $this->notificarBloqueio('Não foi possível matricular', $e->getMessage());

            return;
        }

        Notification::make()
            ->title('Lead marcado como matriculado!')
            ->success()
            ->send();
    }

    private function notificarBloqueio(string $titulo, string $mensagem): void
    {
        Notification::make()
            ->title($titulo)
            ->body($mensagem)
            ->warning()
            ->send();
    }

    /**
     * Abre e inicializa o modal de motivo de perda para o lead selecionado.
     * Protegido: só é chamado por `updateRecordStatus`, que já autorizou a alteração.
     */
    protected function abrirModalPerda(Interessado $record, StatusInteressado $novoStatus): void
    {
        $this->leadPerdaId = $record->id;
        $this->statusPerdaId = $novoStatus->id;
        $this->leadPerdaNome = $record->pessoa?->nome ?? 'Interessado';
        $this->statusPerdaNome = $novoStatus->nome;
        $this->motivoPerda = $record->motivo_perda ?? '';
        $this->concorrenteId = $record->concorrente_id;
        $this->concorrentePerda = null;
        $this->fatorDecisivoConcorrente = $record->fator_decisivo_concorrente;
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
            'motivoPerda' => ['required', 'string', Rule::in(array_keys(Interessado::MOTIVOS_PERDA))],
            'concorrenteId' => ['nullable', 'integer', 'exists:crm_concorrentes,id'],
            'concorrentePerda' => ['nullable', 'string', 'max:255'],
            'fatorDecisivoConcorrente' => ['nullable', 'string', 'max:255'],
            'observacoesPerda' => ['nullable', 'string', 'max:1000'],
        ], [
            'motivoPerda.required' => 'O motivo da perda é obrigatório para registrar o descarte do lead.',
            'motivoPerda.in' => 'Selecione um dos motivos de perda da lista.',
        ]);

        $record = Interessado::find($this->leadPerdaId);
        $novoStatus = StatusInteressado::find($this->statusPerdaId);

        if (! $record || ! $novoStatus || ! $novoStatus->isPerda()) {
            $this->fecharModalPerda();

            return;
        }

        if (! $this->podeAlterar($record)) {
            $this->fecharModalPerda();
            $this->notificarSemPermissao();

            return;
        }

        // Escola concorrente: a cadastrada em Battlecards (FK) ou, na falta, o nome digitado à mão.
        $concorrenteModel = $this->concorrenteId ? Concorrente::find($this->concorrenteId) : null;
        $nomeConcorrente = $concorrenteModel?->nome ?? (filled($this->concorrentePerda) ? trim($this->concorrentePerda) : null);

        try {
            $motivoFinal = app(LeadFunilService::class)->marcarComoPerdido(
                $record,
                $novoStatus,
                $this->motivoPerda,
                $nomeConcorrente,
                $this->observacoesPerda,
                auth()->id(),
                atributosExtras: [
                    'concorrente_id' => $this->concorrenteId,
                    'fator_decisivo_concorrente' => $this->fatorDecisivoConcorrente,
                    'detalhes_concorrencia' => $this->observacoesPerda,
                ],
                relatoExtra: filled($this->fatorDecisivoConcorrente) ? "Fator decisivo: {$this->fatorDecisivoConcorrente}." : null,
            );
        } catch (DomainException $e) {
            $this->fecharModalPerda();
            $this->notificarBloqueio('Não foi possível marcar como perdido', $e->getMessage());

            return;
        }

        $this->fecharModalPerda();

        Notification::make()
            ->title('Lead marcado como perdido!')
            ->body("O motivo \"{$motivoFinal}\" foi registrado no histórico do interessado.")
            ->warning()
            ->send();
    }

    /**
     * Autorização por registro (policy): o Kanban expõe métodos Livewire públicos, então a checagem
     * não pode depender de o botão estar escondido na tela.
     */
    private function podeAlterar(Interessado $record): bool
    {
        return (bool) auth()->user()?->can('update', $record);
    }

    private function notificarSemPermissao(): void
    {
        Notification::make()
            ->title('Sem permissão')
            ->body('Você não tem permissão para alterar leads no funil de vendas.')
            ->danger()
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
        $html .= '<li><strong>🛡️ Battlecards & Objeções:</strong> Acesse no topo a inteligência comparativa com colégios concorrentes, matriz de contorno de objeções com roteiros verbais, perguntas de virada e radar de perdas.</li>';
        $html .= '<li><strong>🔥 Alertas de Escassez nos Cards:</strong> As séries pretendidas nos cards mostram alertas dinâmicos de vagas restantes (ex: <em>Esgotado</em>, <em>Últimas vagas</em>, <em>Vagas limitadas</em>).</li>';
        $html .= '<li><strong>Visualização:</strong> Cada coluna representa um status do funil. Os cards mostram o interessado, origem, dependentes e próximo contato.</li>';
        $html .= '<li><strong>Arrastar e Soltar:</strong> Mova os cards entre colunas para atualizar o status do lead.</li>';
        $html .= '<li><strong>🎓 Matrícula:</strong> Arrastar um card para <em>Matriculado</em> abre o Assistente de Matrícula já preenchido com os dados do lead (quem não tem acesso ao assistente usa o atalho "Marcar matriculado"). O lead só vira matriculado quando a matrícula é concluída, e um lead matriculado não volta no funil.</li>';
        $html .= '<li><strong>🛑 Motivo de Perda Obrigatório (Stage Gate):</strong> Ao arrastar um lead para uma coluna de encerramento/perda (ex: <em>Desistente</em>, <em>Perdido</em>), o sistema abre obrigatoriamente um modal para registro da razão da perda, concorrente e anotações, qualificando a inteligência comercial da instituição.</li>';
        $html .= '<li><strong>Carregar mais:</strong> Cada coluna mostra primeiro os leads mais urgentes. O número no topo da coluna é o total real da etapa; se houver mais leads, use o botão <em>Carregar mais</em> no fim da coluna. Matriculados e perdidos aparecem só dos últimos '.(int) config('crm.kanban.dias_finalizados', 90).' dias — os mais antigos ficam na aba <em>Finalizados</em> da lista.</li>';
        $html .= '<li><strong>Cards em Vermelho:</strong> Indicam leads com contato atrasado (urgente!).</li>';
        $html .= '<li><strong>Filtro de Consultor:</strong> Use o botão "Filtrar Consultor" para ver apenas os leads de um consultor específico.</li>';

        if ($canUpdate) {
            $html .= '<li><strong>Editar:</strong> Clique no ícone de lápis para abrir os detalhes do lead.</li>';
        }

        $html .= '</ul>';

        return $html;
    }
}

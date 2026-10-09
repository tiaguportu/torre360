<?php

declare(strict_types=1);

namespace App\Filament\Resources\Interessados\RelationManagers;

use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\TipoContatoInteressado;
use App\Services\Customer360TimelineService;
use App\Services\LeadScoreService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;

class TimelineRelationManager extends RelationManager
{
    protected static string $relationship = 'historicos';

    protected static ?string $title = 'Linha do Tempo 360°';

    protected static string|BackedEnum|null $icon = 'heroicon-o-clock';

    /**
     * Quantidade de eventos exibidos por vez no feed ("Carregar mais" soma outro lote).
     */
    public const EVENTOS_POR_LOTE = 20;

    /**
     * Filtro ativo de categoria de eventos.
     */
    public string $filtroCategoria = 'todos';

    /**
     * Termo de busca textual no feed.
     */
    public string $termoBusca = '';

    /**
     * Quantos eventos do feed estão visíveis no momento.
     */
    public int $limiteEventos = self::EVENTOS_POR_LOTE;

    /**
     * Controle de visibilidade do formulário de registro rápido.
     */
    public bool $mostrarFormularioRapido = true;

    // Campos do Registro Rápido de Interação
    public ?int $novoTipoContatoId = null;

    public string $novoRelato = '';

    public ?string $novoResultado = 'retornar';

    public ?string $novaDataProximoContato = null;

    public ?int $novaDuracaoMinutos = null;

    public function mount(): void
    {
        parent::mount();

        // Pré-seleciona WhatsApp por padrão, ou o primeiro canal cadastrado
        $tipoPadrao = TipoContatoInteressado::where('nome', 'like', '%WhatsApp%')->first()
            ?? TipoContatoInteressado::first();

        $this->novoTipoContatoId = $tipoPadrao?->id;
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        if (! $ownerRecord instanceof Interessado) {
            return null;
        }

        $total = $ownerRecord->historicos()->count()
            + $ownerRecord->visitas()->count()
            + $ownerRecord->documentosInseridos()->count();

        return $total > 0 ? (string) $total : null;
    }

    public static function getBadgeColor(Model $ownerRecord, string $pageClass): ?string
    {
        return 'primary';
    }

    public static function getBadgeTooltip(Model $ownerRecord, string $pageClass): string|Htmlable|null
    {
        return 'Total de eventos e interações registradas na Linha do Tempo 360°';
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                SchemaView::make('filament.resources.interessados.relation-managers.timeline-feed')
                    ->viewData([
                        'livewire' => $this,
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        // Mantém configuração de tabela compatível com a regra obrigatória de responsividade
        return $table
            ->recordTitleAttribute('relato')
            ->stackedOnMobile();
    }

    /**
     * Retorna os eventos da timeline usando o serviço agregador (com memoização por request).
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function timeline(): Collection
    {
        /** @var Interessado $interessado */
        $interessado = $this->getOwnerRecord();

        return app(Customer360TimelineService::class)->obterTimeline(
            interessado: $interessado,
            filtroCategoria: $this->filtroCategoria,
            busca: $this->termoBusca
        );
    }

    /**
     * Retorna as métricas consolidadas do lead para a barra superior.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function metricas(): array
    {
        /** @var Interessado $interessado */
        $interessado = $this->getOwnerRecord();

        return app(Customer360TimelineService::class)->obterResumoMetricas($interessado);
    }

    /**
     * Retorna a lista de tipos de contato cadastrados para o seletor.
     *
     * @return Collection<int, TipoContatoInteressado>
     */
    #[Computed]
    public function tiposContato(): Collection
    {
        return TipoContatoInteressado::orderBy('nome')->get();
    }

    /**
     * Indica se o usuário atual tem permissão para registrar novas interações.
     */
    #[Computed]
    public function podeRegistrar(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('super_admin')) {
            return true;
        }

        return $user->can('Update:Interessado') || $user->can('Create:HistoricoContato');
    }

    /**
     * Altera a categoria filtrada no feed.
     */
    public function filtrar(string $categoria): void
    {
        $this->filtroCategoria = $categoria;
        $this->limiteEventos = self::EVENTOS_POR_LOTE;
    }

    /**
     * Limpa busca e filtros ativos.
     */
    public function limparFiltros(): void
    {
        $this->filtroCategoria = 'todos';
        $this->termoBusca = '';
        $this->limiteEventos = self::EVENTOS_POR_LOTE;
    }

    /**
     * Uma nova busca recomeça a paginação do feed.
     */
    public function updatedTermoBusca(): void
    {
        $this->limiteEventos = self::EVENTOS_POR_LOTE;
    }

    /**
     * Exibe mais um lote de eventos mais antigos no feed.
     */
    public function carregarMais(): void
    {
        $this->limiteEventos += self::EVENTOS_POR_LOTE;
    }

    /**
     * Alterna a exibição da barra de interação rápida.
     */
    public function toggleFormularioRapido(): void
    {
        $this->mostrarFormularioRapido = ! $this->mostrarFormularioRapido;
    }

    /**
     * Registra uma nova interação rápida diretamente no topo do feed.
     */
    public function registrarContatoRapido(): void
    {
        abort_unless(
            $this->podeRegistrar(),
            403,
            'Você não possui permissão para registrar interações neste lead.'
        );

        $this->validate([
            'novoTipoContatoId' => ['required', 'exists:tipo_contato_interessado,id'],
            'novoRelato' => ['required', 'string', 'min:3'],
            'novoResultado' => ['nullable', 'string'],
            'novaDataProximoContato' => ['nullable', 'date'],
            'novaDuracaoMinutos' => ['nullable', 'integer', 'min:1'],
        ], [
            'novoTipoContatoId.required' => 'Selecione o canal da interação.',
            'novoRelato.required' => 'Digite o resumo do que foi conversado ou acordado.',
            'novoRelato.min' => 'O resumo deve ter no mínimo 3 caracteres.',
        ]);

        /** @var Interessado $interessado */
        $interessado = $this->getOwnerRecord();

        DB::transaction(function () use ($interessado): void {
            // 1. Grava no Histórico de Contatos
            HistoricoContato::create([
                'interessado_id' => $interessado->id,
                'usuario_id' => auth()->id(),
                'tipo_contato_interessado_id' => $this->novoTipoContatoId,
                'relato' => trim($this->novoRelato),
                'data_contato' => now(),
                'resultado' => $this->novoResultado,
                'duracao_minutos' => $this->novaDuracaoMinutos,
            ]);

            // 2. Se informada nova data de próximo contato, atualiza no Interessado
            if (! empty($this->novaDataProximoContato)) {
                $interessado->update([
                    'data_proximo_contato' => Carbon::parse($this->novaDataProximoContato),
                ]);
            }

            // 3. Recalcula o Lead Score com base na nova interação
            LeadScoreService::recalcular($interessado);
        });

        // 4. Limpa campos do formulário
        $this->novoRelato = '';
        $this->novaDataProximoContato = null;
        $this->novaDuracaoMinutos = null;
        $this->novoResultado = 'retornar';

        Notification::make()
            ->title('⚡ Interação registrada com sucesso!')
            ->body('O contato foi adicionado à Linha do Tempo e o Lead Score foi recalculado.')
            ->success()
            ->send();
    }

    /**
     * Action de Ajuda do cabeçalho conforme diretrizes obrigatórias do sistema.
     */
    public function ajudaAction(): Action
    {
        return Action::make('ajuda')
            ->label('Ajuda')
            ->icon('heroicon-o-question-mark-circle')
            ->color('gray')
            ->modalHeading('Ajuda: Linha do Tempo Omnichannel 360°')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->form([
                ViewField::make('help_content')
                    ->view('filament.components.help-content')
                    ->viewData([
                        'content' => $this->getHelpContent(),
                    ]),
            ]);
    }

    /**
     * Gera o conteúdo em HTML da Ajuda com verificação de permissões do Shield.
     */
    private function getHelpContent(): string
    {
        $podeRegistrar = $this->podeRegistrar();
        $lote = self::EVENTOS_POR_LOTE;

        $html = '<p>A <strong>Linha do Tempo Omnichannel Interativa (Unified Customer 360 Feed)</strong> centraliza toda a jornada de relacionamento da família em um único feed cronológico unificado.</p>';
        $html .= '<h3>O que você pode fazer nesta tela:</h3>';
        $html .= '<ul>';

        $html .= '<li><strong>📊 Indicadores 360°:</strong> No cabeçalho você acompanha temperatura, Lead Score, próximo e último contato, NPS da visita e documentos verificados. O cartão de retorno fica vermelho quando o contato está em atraso.</li>';

        if ($podeRegistrar) {
            $html .= '<li><strong>⚡ Registrar nova interação:</strong> No bloco logo abaixo do cabeçalho, escolha o canal, escreva o que foi conversado ou acordado e defina o resultado, a duração e o próximo retorno (use os atalhos <em>Amanhã</em>, <em>Em 3 dias</em> ou <em>Em 1 semana</em>) sem precisar navegar entre telas.</li>';
        }

        $html .= '<li><strong>💬 Contatos & Mensagens:</strong> Acompanhe todas as interações humanas e mensagens geradas pelos copilotos de IA no WhatsApp com indicação do consultor e duração.</li>';

        $html .= '<li><strong>🏫 Visitas Escolares & NPS:</strong> Veja quando o tour escolar foi agendado e realizado. Para visitas concluídas, confira a nota NPS (0-10), as avaliações de atendimento, infraestrutura e proposta pedagógica, além do feedback textual da família. Se a pesquisa ainda não foi respondida, você pode reenviar o link com 1 clique.</li>';

        $html .= '<li><strong>📑 Documentos & Parecer de IA:</strong> Monitore o envio de documentos da família com o parecer pericial emitido pelo Gemini Vision (nitidez, tipologia e dados extraídos como CPF e certidão).</li>';

        $html .= '<li><strong>🔄 Etapas & Funil:</strong> Visualize as mudanças de status no funil de vendas (Kanban) como <em>etapa anterior → nova etapa</em>, o motivo de perda/descarte e os ajustes na temperatura comercial auditados automaticamente pelo sistema.</li>';

        $html .= '<li><strong>🔍 Filtros em Tempo Real:</strong> Use os botões acima do feed para filtrar apenas Contatos, Visitas, Documentos ou Etapas (os botões mostram quantos registros há em cada categoria) ou pesquise qualquer palavra-chave no campo de busca. O atalho <em>Limpar filtros</em> volta à visão completa.</li>';

        $html .= '<li><strong>📅 Feed por dia:</strong> Os eventos aparecem do mais recente ao mais antigo, separados por dia, e as visitas futuras ganham a marca <em>Agendado</em>. São exibidos '.$lote.' eventos por vez: use o botão <em>Carregar mais</em> no fim da lista para ver os anteriores.</li>';

        if ($podeRegistrar) {
            $html .= '<li><strong>Pontuação do Lead (Lead Score):</strong> Toda interação registrada recalcula instantaneamente o Lead Score e a temperatura do interessado.</li>';
        }

        $html .= '</ul>';
        $html .= '<p><strong>Dica de Vendas:</strong> Monitore o cartão de <em>Próximo Contato</em> no cabeçalho. Se estiver em atraso, faça o retorno imediato pelo botão <strong>Retornar no WhatsApp</strong>, no topo da tela!</p>';

        return $html;
    }
}

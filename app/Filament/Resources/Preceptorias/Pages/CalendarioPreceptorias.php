<?php

namespace App\Filament\Resources\Preceptorias\Pages;

use App\Filament\Resources\Preceptorias\PreceptoriaResource;
use App\Filament\Resources\Preceptorias\Schemas\PreceptoriaRecorrenciaForm;
use App\Models\CicloPreceptoria;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Preceptoria;
use App\Services\PreceptoriaAgendaService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Calendário para organizar as preceptorias: arrastar muda dia/horário, esticar muda a duração,
 * selecionar um intervalo vazio cria um horário e "Gerar recorrência" cria uma série numa janela.
 */
class CalendarioPreceptorias extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = PreceptoriaResource::class;

    protected string $view = 'filament.resources.preceptorias.pages.calendario-preceptorias';

    protected static ?string $title = 'Calendário de Preceptorias';

    protected const COR_DISPONIVEL = '#6b7280';

    protected const COR_AGENDADO = '#059669';

    /** @var array<string, mixed>|null */
    public ?array $filtros = [];

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->can('viewAny', Preceptoria::class) ?? false;
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'md' => 3])
                    ->schema([
                        Select::make('ciclo_preceptoria_id')
                            ->label('Ciclo de Preceptoria')
                            ->options(fn () => CicloPreceptoria::query()->orderByDesc('data_inicio')->orderBy('nome')->pluck('nome', 'id'))
                            ->placeholder('Todos os ciclos')
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn ($state) => $this->recarregarCalendario($state)),

                        Select::make('professores')
                            ->label('Professores')
                            ->multiple()
                            ->options(fn () => Pessoa::query()
                                ->whereIn('id', PreceptoriaResource::getEloquentQuery()->select('professor_id'))
                                ->orderBy('nome')
                                ->pluck('nome', 'id'))
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn () => $this->recarregarCalendario())
                            ->hidden(fn () => session('active_role') === 'professor'),

                        Select::make('status')
                            ->label('Situação')
                            ->options([
                                'agendado' => 'Agendadas (com aluno)',
                                'disponivel' => 'Livres (sem aluno)',
                            ])
                            ->placeholder('Todas')
                            ->live()
                            ->afterStateUpdated(fn () => $this->recarregarCalendario()),
                    ]),
            ])
            ->statePath('filtros');
    }

    /**
     * Eventos do período visível, já dentro do que o usuário pode ver (mesma regra da listagem).
     *
     * @param  string  $inicio  Y-m-d, primeiro dia visível
     * @param  string  $fim  Y-m-d, primeiro dia depois do último visível
     * @return array<int, array<string, mixed>>
     */
    public function getEventos(string $inicio, string $fim): array
    {
        $de = $this->lerDia($inicio);
        $ate = $this->lerDia($fim);

        if (! $de || ! $ate || ! static::canAccess()) {
            return [];
        }

        $podeEditar = (bool) auth()->user()?->can('Update:Preceptoria');

        $preceptorias = $this->queryFiltrada()
            ->with(['professor', 'matricula.pessoa', 'cicloPreceptoria'])
            ->whereDate('data', '>=', $de->toDateString())
            ->whereDate('data', '<', $ate->toDateString())
            ->orderBy('data')
            ->orderBy('hora_inicio')
            ->get();

        $eventos = $preceptorias->map(function (Preceptoria $p) use ($podeEditar) {
            $agendado = $p->matricula_id !== null;
            $dia = $p->data->toDateString();
            $inicio = $p->hora_inicio->format('H:i:s');
            $fim = $p->hora_fim
                ? $p->hora_fim->format('H:i:s')
                : $p->hora_inicio->copy()->addMinutes(PreceptoriaAgendaService::DURACAO_PADRAO_MINUTOS)->format('H:i:s');
            $cor = $agendado ? self::COR_AGENDADO : self::COR_DISPONIVEL;
            $professor = $p->professor?->nome ?? 'Sem professor';
            $aluno = $p->matricula?->pessoa?->nome;

            return [
                'id' => (string) $p->id,
                'title' => $agendado ? "{$professor} · {$aluno}" : "{$professor} · Livre",
                'start' => "{$dia}T{$inicio}",
                'end' => "{$dia}T{$fim}",
                'backgroundColor' => $cor,
                'borderColor' => $cor,
                'textColor' => '#ffffff',
                'editable' => $podeEditar,
                'extendedProps' => [
                    'gerenciavel' => $podeEditar,
                    'agendado' => $agendado,
                    'professor' => $professor,
                    'aluno' => $aluno,
                    'ciclo' => $p->cicloPreceptoria?->nome,
                    'horario' => $p->hora_inicio->format('H:i').($p->hora_fim ? ' – '.$p->hora_fim->format('H:i') : ''),
                    'url' => $podeEditar
                        ? PreceptoriaResource::getUrl('edit', ['record' => $p])
                        : PreceptoriaResource::getUrl('view', ['record' => $p]),
                ],
            ];
        });

        return $eventos->concat($this->eventosDeDiasEspeciais($de, $ate))->values()->all();
    }

    /**
     * Chamado pelo calendário ao soltar/esticar um evento. Devolve ok=false para o calendário desfazer o gesto.
     *
     * @param  string  $inicio  Y-m-d\TH:i:s no horário local da escola
     * @return array{ok: bool, mensagem: string}
     */
    public function reagendarPreceptoria(int $id, string $inicio, ?string $fim = null): array
    {
        if (! auth()->user()?->can('Update:Preceptoria')) {
            return $this->recusar('Você não tem permissão para reagendar preceptorias.');
        }

        $preceptoria = PreceptoriaResource::getEloquentQuery()->with(['cicloPreceptoria'])->find($id);

        if (! $preceptoria) {
            return $this->recusar('Preceptoria não encontrada.');
        }

        $resultado = app(PreceptoriaAgendaService::class)->reagendar($preceptoria, $inicio, $fim);

        if (! $resultado['ok']) {
            return $this->recusar($resultado['mensagem']);
        }

        Notification::make()
            ->title($resultado['mensagem'])
            ->body($resultado['notificados'] ? "{$resultado['notificados']} pessoa(s) avisada(s) da mudança." : null)
            ->success()
            ->send();

        if ($resultado['aviso']) {
            Notification::make()->title($resultado['aviso'])->warning()->send();
        }

        return ['ok' => true, 'mensagem' => $resultado['mensagem']];
    }

    /**
     * Mostrado ao selecionar um intervalo vazio no calendário (o JS passa data e horários como argumentos).
     */
    public function criarPreceptoriaAction(): Action
    {
        return Action::make('criarPreceptoria')
            ->modalHeading('Novo horário de preceptoria')
            ->modalDescription('Horário livre na agenda do professor; o aluno é agendado depois.')
            ->modalSubmitActionLabel('Criar horário')
            ->visible(fn () => (bool) auth()->user()?->can('Create:Preceptoria'))
            ->schema([
                $this->campoCiclo(),
                $this->campoProfessor(),
                DatePicker::make('data')
                    ->label('Data')
                    ->required()
                    ->native(false)
                    ->displayFormat('d/m/Y'),
                TimePicker::make('hora_inicio')
                    ->label('Hora Início')
                    ->required()
                    ->seconds(false),
                TimePicker::make('hora_fim')
                    ->label('Hora Fim')
                    ->seconds(false),
            ])
            ->fillForm(fn (array $arguments) => [
                'ciclo_preceptoria_id' => $this->filtros['ciclo_preceptoria_id'] ?? null,
                'professor_id' => $this->professorPadrao(),
                'data' => $arguments['data'] ?? null,
                'hora_inicio' => $arguments['hora_inicio'] ?? null,
                'hora_fim' => $arguments['hora_fim'] ?? null,
            ])
            ->action(function (array $data, Action $action) {
                $conflito = app(PreceptoriaAgendaService::class)->conflitosDoProfessor(
                    (int) $data['professor_id'],
                    [Carbon::parse($data['data'])->toDateString()],
                    $data['hora_inicio'],
                    $data['hora_fim'] ?? null,
                );

                if ($conflito !== []) {
                    Notification::make()
                        ->title('O professor já tem uma preceptoria nesse horário.')
                        ->danger()
                        ->send();

                    $action->halt();
                }

                Preceptoria::create($data);

                Notification::make()->title('Horário criado.')->success()->send();

                $this->recarregarCalendario();
            });
    }

    /**
     * Aberto ao clicar numa preceptoria (o JS passa o id como argumento): troca o professor e/ou o aluno,
     * ou libera o horário. Quem pode editar mexe no professor; quem também pode agendar mexe no aluno.
     */
    public function gerenciarPreceptoriaAction(): Action
    {
        return Action::make('gerenciarPreceptoria')
            ->modalHeading('Gerenciar preceptoria')
            ->modalDescription(function (array $arguments) {
                $preceptoria = $this->preceptoriaGerenciavel($arguments['id'] ?? null);

                return $preceptoria
                    ? sprintf(
                        '%s às %s%s',
                        $preceptoria->data->format('d/m/Y'),
                        $preceptoria->hora_inicio->format('H:i'),
                        $preceptoria->cicloPreceptoria ? " · {$preceptoria->cicloPreceptoria->nome}" : '',
                    )
                    : 'Preceptoria não encontrada.';
            })
            ->modalSubmitActionLabel('Salvar')
            ->modalWidth('2xl')
            ->visible(fn () => (bool) auth()->user()?->can('Update:Preceptoria'))
            ->schema(function (array $arguments) {
                $preceptoria = $this->preceptoriaGerenciavel($arguments['id'] ?? null);

                if (! $preceptoria) {
                    return [];
                }

                return [
                    $this->campoProfessor()
                        ->helperText('Trocar o professor avisa o anterior, o novo e, se houver aluno, ele e os responsáveis.'),

                    Select::make('matricula_id')
                        ->label('Aluno (matrícula)')
                        ->placeholder('Livre (sem aluno)')
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search) => $this->matriculasParaBuscar($search))
                        ->getOptionLabelUsing(fn ($value) => Matricula::with(['pessoa', 'turma', 'periodoLetivo'])->find($value)?->label_exibicao)
                        ->helperText('Escolha outro aluno para trocar ou esvazie para liberar o horário; quem sai e quem entra são avisados.')
                        ->visible(fn () => (bool) auth()->user()?->can('Agendar:Preceptoria')),

                    Section::make('Últimas alterações')
                        ->description('O histórico completo fica no registro da preceptoria.')
                        ->collapsible()
                        ->collapsed()
                        ->schema([
                            ViewField::make('historico')
                                ->view('filament.components.preceptoria-historico')
                                ->viewData(['preceptoria' => $preceptoria, 'limite' => 5]),
                        ]),
                ];
            })
            ->fillForm(function (array $arguments) {
                $preceptoria = $this->preceptoriaGerenciavel($arguments['id'] ?? null);

                return [
                    'professor_id' => $preceptoria?->professor_id,
                    'matricula_id' => $preceptoria?->matricula_id,
                ];
            })
            ->extraModalFooterActions(function (array $arguments) {
                $preceptoria = $this->preceptoriaGerenciavel($arguments['id'] ?? null);

                return $preceptoria
                    ? [
                        Action::make('abrirRegistro')
                            ->label('Abrir registro completo')
                            ->color('gray')
                            ->url(PreceptoriaResource::getUrl('edit', ['record' => $preceptoria])),
                    ]
                    : [];
            })
            ->action(function (array $data, array $arguments, Action $action) {
                $preceptoria = $this->preceptoriaGerenciavel($arguments['id'] ?? null);

                if (! $preceptoria) {
                    $this->recusar('Preceptoria não encontrada.');
                    $this->recarregarCalendario();

                    return;
                }

                // sem o campo (quem não pode agendar), o aluno fica como está
                $mexeNoAluno = array_key_exists('matricula_id', $data);

                $resultado = app(PreceptoriaAgendaService::class)->atualizarParticipantes(
                    $preceptoria,
                    (int) $data['professor_id'],
                    $mexeNoAluno && filled($data['matricula_id']) ? (int) $data['matricula_id'] : null,
                    alterarAluno: $mexeNoAluno,
                );

                if (! $resultado['ok']) {
                    $this->recusar($resultado['mensagem']);

                    $action->halt();
                }

                Notification::make()
                    ->title($resultado['mensagem'])
                    ->body($resultado['notificados'] ? "{$resultado['notificados']} pessoa(s) avisada(s) da mudança." : null)
                    ->success()
                    ->send();

                $this->recarregarCalendario();
            });
    }

    /**
     * Cria a série recorrente numa janela (mesmas regras do cadastro de preceptorias).
     */
    public function gerarRecorrenciaAction(): Action
    {
        return Action::make('gerarRecorrencia')
            ->label('Gerar recorrência')
            ->icon('heroicon-o-arrow-path')
            ->color('info')
            ->modalHeading('Gerar preceptorias recorrentes')
            ->modalDescription('Repete o mesmo horário numa janela de datas, pulando o que for desconsiderado.')
            ->modalSubmitActionLabel('Gerar')
            ->modalWidth('3xl')
            ->visible(fn () => (bool) auth()->user()?->can('Create:Preceptoria'))
            ->schema([
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    $this->campoCiclo()
                        ->live()
                        ->afterStateUpdated(fn ($get, $set) => PreceptoriaRecorrenciaForm::sugerirJanelaDoCiclo($get, $set)),
                    $this->campoProfessor()->live(),
                    TimePicker::make('hora_inicio')->label('Hora Início')->required()->seconds(false)->live(),
                    TimePicker::make('hora_fim')->label('Hora Fim')->seconds(false)->live(),
                ]),
                Grid::make(['default' => 1, 'md' => 3])->schema(PreceptoriaRecorrenciaForm::componentes()),
            ])
            ->fillForm(fn () => [
                'ciclo_preceptoria_id' => $this->filtros['ciclo_preceptoria_id'] ?? null,
                'professor_id' => $this->professorPadrao(),
            ])
            ->action(function (array $data, Action $action) {
                if (PreceptoriaRecorrenciaForm::criar($data) === null) {
                    $action->halt();
                }

                $this->recarregarCalendario();
            });
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('lista')
                ->label('Ver em lista')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->url(fn () => PreceptoriaResource::getUrl('index')),

            $this->gerarRecorrenciaAction(),

            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Calendário de Preceptorias')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->schema([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                        ]),
                ]),
        ];
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();

        $html = '<p>Nesta página você organiza as preceptorias num calendário: cada bloco é uma preceptoria, com o professor preceptor e, quando já agendada, o aluno.</p>';
        $html .= '<h3>O que você pode fazer?</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Filtrar:</strong> escolha o ciclo de preceptoria, os professores e a situação (agendadas ou livres). Ao escolher um ciclo fora do período atual, o calendário vai para o início dele.</li>';
        $html .= '<li><strong>Trocar a visão:</strong> mês, semana, dia ou lista, nos botões do topo do calendário. Dias em vermelho são feriados nacionais ou dias não letivos.</li>';

        if ($user?->can('Update:Preceptoria')) {
            $html .= '<li><strong>Mover:</strong> arraste um bloco para outro dia ou horário. Se o professor já tiver preceptoria no novo horário, o sistema recusa e o bloco volta. Soltar num fim de semana, feriado ou dia não letivo é permitido, mas o sistema avisa.</li>';
            $html .= '<li><strong>Mudar a duração:</strong> estique a borda inferior do bloco (visões de semana e dia).</li>';
            $html .= '<li><strong>Preceptoria já agendada:</strong> ao mover, o sistema pede confirmação e avisa o aluno, os responsáveis e o professor do novo horário. Se o horário estava livre, só o professor é avisado (e só quando outra pessoa fez a mudança).</li>';
            $html .= '<li><strong>Trocar professor ou aluno:</strong> clique no bloco. Escolha outro professor (que não pode ter preceptoria no mesmo horário) e, se você pode agendar, outro aluno (que não pode ter outra preceptoria no mesmo ciclo) ou deixe o aluno vazio para liberar o horário. Quem perde e quem ganha o horário é avisado. O botão "Abrir registro completo" leva à edição de todos os campos.</li>';
            $html .= '<li><strong>Histórico:</strong> as últimas alterações aparecem no modal; o histórico completo, com os avisos enviados, está no botão "Histórico de Alterações" do registro.</li>';
        }

        if ($user?->can('Create:Preceptoria')) {
            $html .= '<li><strong>Criar um horário:</strong> nas visões de semana e dia, clique e arraste num espaço vazio para abrir o cadastro já com o dia e a hora escolhidos.</li>';
            $html .= '<li><strong>Gerar recorrência:</strong> repete o mesmo horário numa janela de datas (ex.: toda terça e quinta do ciclo). Por padrão ficam de fora fins de semana, feriados nacionais e dias não letivos; a prévia mostra o que será criado e o que foi ignorado.</li>';
        }

        $html .= '<li><strong>Abrir o registro:</strong> '.($user?->can('Update:Preceptoria')
            ? 'no modal de gestão, use "Abrir registro completo".'
            : 'clique no bloco para abrir a preceptoria.').'</li>';
        $html .= '</ul>';

        return $html;
    }

    /**
     * Avisa o calendário (JS) para buscar os eventos de novo, opcionalmente indo para o início do ciclo.
     */
    private function recarregarCalendario(int|string|null $cicloId = null): void
    {
        $irPara = null;

        if ($cicloId && ($ciclo = CicloPreceptoria::find($cicloId)) && $ciclo->data_inicio && $ciclo->data_fim) {
            $hoje = today();
            $irPara = $hoje->between($ciclo->data_inicio, $ciclo->data_fim) ? null : $ciclo->data_inicio->toDateString();
        }

        $this->dispatch('preceptorias-recarregar', irPara: $irPara);
    }

    /**
     * A preceptoria que o usuário pode ver (mesma regra da listagem), com o que o modal de gestão mostra.
     */
    private function preceptoriaGerenciavel(int|string|null $id): ?Preceptoria
    {
        if (blank($id)) {
            return null;
        }

        return PreceptoriaResource::getEloquentQuery()
            ->with(['professor', 'matricula.pessoa', 'cicloPreceptoria'])
            ->find($id);
    }

    /**
     * Matrículas que o usuário pode agendar cujo aluno tem o nome buscado.
     *
     * @return array<int, string>
     */
    private function matriculasParaBuscar(string $busca): array
    {
        return app(PreceptoriaAgendaService::class)
            ->matriculasAcessiveis(auth()->user(), session('active_role'))
            ->whereHas('pessoa', fn (Builder $query) => $query->where('nome', 'like', "%{$busca}%"))
            ->with(['pessoa', 'turma', 'periodoLetivo'])
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Matricula $matricula) => [$matricula->id => $matricula->label_exibicao])
            ->all();
    }

    private function queryFiltrada(): Builder
    {
        $query = PreceptoriaResource::getEloquentQuery();

        if (filled($this->filtros['ciclo_preceptoria_id'] ?? null)) {
            $query->where('ciclo_preceptoria_id', $this->filtros['ciclo_preceptoria_id']);
        }

        if (filled($this->filtros['professores'] ?? null)) {
            $query->whereIn('professor_id', $this->filtros['professores']);
        }

        return match ($this->filtros['status'] ?? null) {
            'agendado' => $query->whereNotNull('matricula_id'),
            'disponivel' => $query->whereNull('matricula_id'),
            default => $query,
        };
    }

    /**
     * Feriados nacionais e dias não letivos como faixas de fundo, para quem organiza a agenda enxergar.
     *
     * @return array<int, array<string, mixed>>
     */
    private function eventosDeDiasEspeciais(Carbon $de, Carbon $ate): array
    {
        $servico = app(PreceptoriaAgendaService::class);
        $ultimo = $ate->copy()->subDay();

        $periodoLetivoId = filled($this->filtros['ciclo_preceptoria_id'] ?? null)
            ? CicloPreceptoria::find($this->filtros['ciclo_preceptoria_id'])?->periodo_letivo_id
            : null;

        $dias = $servico->diasNaoLetivos($de, $ultimo, $periodoLetivoId);
        foreach ($servico->feriadosNacionais($de, $ultimo) as $data => $nome) {
            $dias[$data] = "Feriado: {$nome}";
        }

        return collect($dias)
            ->map(fn (string $motivo, string $data) => [
                'id' => "especial-{$data}",
                'title' => $motivo,
                'start' => $data,
                'allDay' => true,
                'display' => 'background',
                'backgroundColor' => '#fecaca',
                'editable' => false,
                'extendedProps' => ['especial' => true, 'motivo' => $motivo],
            ])
            ->values()
            ->all();
    }

    private function campoCiclo(): Select
    {
        return Select::make('ciclo_preceptoria_id')
            ->label('Ciclo de Preceptoria')
            ->options(fn () => CicloPreceptoria::query()->orderByDesc('data_inicio')->orderBy('nome')->pluck('nome', 'id'))
            ->searchable()
            ->required();
    }

    private function campoProfessor(): Select
    {
        $professor = auth()->user()?->atuaComoProfessor();

        return Select::make('professor_id')
            ->label('Professor(a)')
            ->options(fn () => $professor ? auth()->user()->pessoas->pluck('nome', 'id') : [])
            ->getSearchResultsUsing(fn (string $search) => $professor
                ? auth()->user()->pessoas->filter(fn (Pessoa $p) => str_contains(mb_strtolower($p->nome), mb_strtolower($search)))->pluck('nome', 'id')
                : Pessoa::query()->where('nome', 'like', "%{$search}%")->orderBy('nome')->limit(50)->pluck('nome', 'id'))
            ->getOptionLabelUsing(fn ($value) => Pessoa::find($value)?->nome)
            ->searchable()
            ->required();
    }

    /**
     * Quem atua como professor agenda para si; os demais escolhem. Com um só professor no filtro, sugere ele.
     */
    private function professorPadrao(): ?int
    {
        $user = auth()->user();

        if ($user?->atuaComoProfessor() && $user->pessoas->count() === 1) {
            return $user->pessoas->first()->id;
        }

        $selecionados = $this->filtros['professores'] ?? [];

        return count($selecionados) === 1 ? (int) $selecionados[0] : null;
    }

    private function lerDia(string $valor): ?Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m-d', $valor)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{ok: bool, mensagem: string}
     */
    private function recusar(string $mensagem): array
    {
        Notification::make()->title($mensagem)->danger()->send();

        return ['ok' => false, 'mensagem' => $mensagem];
    }
}

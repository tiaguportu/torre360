<?php

namespace App\Filament\Resources\Preceptorias\Pages;

use App\Exceptions\ConflitoDeHorarioException;
use App\Filament\Resources\Preceptorias\PreceptoriaResource;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Preceptoria;
use App\Notifications\Preceptorias\PreceptoriaNotification;
use App\Services\PreceptoriaAgendaService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class AgendarPreceptoria extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = PreceptoriaResource::class;

    protected string $view = 'filament.pages.agendar-preceptoria';

    protected static ?string $title = 'Agendar Preceptoria';

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->can('Agendar:Preceptoria') ?? false;
    }

    public ?array $data = [];

    public function mount(): void
    {
        // o atalho do painel abre a tela com ?matricula_id=; só vale para uma matrícula que o usuário pode agendar
        $matriculaId = (int) request()->query('matricula_id');
        $acessivel = $matriculaId > 0
            && app(PreceptoriaAgendaService::class)->matriculasAcessiveis(auth()->user(), session('active_role'))->whereKey($matriculaId)->exists();

        $this->form->fill($acessivel ? ['matricula_id' => $matriculaId] : []);
    }

    protected function getMatriculasAcessiveis(): Collection
    {
        return app(PreceptoriaAgendaService::class)
            ->matriculasAcessiveis(auth()->user(), session('active_role'))
            ->with(['pessoa', 'turma', 'periodoLetivo'])
            ->get();
    }

    public function liberarHorario(int $id): void
    {
        $preceptoria = Preceptoria::with(['professor.users', 'matricula.pessoa.responsaveis.users'])->findOrFail($id);

        // o e-mail é montado pela fila, quando o registro já não tem o aluno: o nome vai pronto
        $aluno = $preceptoria->matricula?->pessoa?->nome;

        $this->enviarNotificacoes($preceptoria, 'liberacao', $aluno);

        $preceptoria->update(['matricula_id' => null]);

        app(PreceptoriaAgendaService::class)->registrarHistorico(
            $preceptoria,
            'liberacao_preceptoria',
            'Horário liberado (o aluno era '.($aluno ?? 'não informado').')',
            ['antes' => $aluno, 'depois' => null],
        );

        Notification::make()
            ->title('Agendamento cancelado com sucesso!')
            ->success()
            ->send();

        $this->form->fill(['matricula_id' => $this->data['matricula_id'] ?? null]);
    }

    protected function enviarNotificacoes(Preceptoria $preceptoria, string $tipo, ?string $alunoAnterior = null): void
    {
        // 1) Usuário que realizou o agendamento (Solicitante)
        $solicitante = auth()->user();
        if ($solicitante) {
            $solicitante->notify(new PreceptoriaNotification($preceptoria, $tipo, paraSolicitante: true, alunoAnterior: $alunoAnterior));
        }

        // 2) Usuários relacionados com Pessoa Professor configurado para a Preceptoria escolhida
        if ($preceptoria->professor) {
            $preceptoria->professor->users->each(function ($user) use ($preceptoria, $tipo, $solicitante, $alunoAnterior) {
                if ($user->id !== $solicitante?->id) {
                    $user->notify(new PreceptoriaNotification($preceptoria, $tipo, alunoAnterior: $alunoAnterior));
                }
            });
        }

        // 3) Usuários vinculados a Pessoa Responsável e a Pessoa Aluno relacionados com a Matricula que foi configurada
        if ($preceptoria->matricula?->pessoa) {
            $alunoPessoa = $preceptoria->matricula->pessoa;

            // Notificar Usuários do Aluno (se houver)
            $alunoPessoa->users->each(function ($user) use ($preceptoria, $tipo, $solicitante, $alunoAnterior) {
                if ($user->id !== $solicitante?->id) {
                    $user->notify(new PreceptoriaNotification($preceptoria, $tipo, alunoAnterior: $alunoAnterior));
                }
            });

            // Notificar Usuários dos Responsáveis
            $alunoPessoa->responsaveis->each(function ($responsavel) use ($preceptoria, $tipo, $solicitante, $alunoAnterior) {
                $responsavel->users->each(function ($user) use ($preceptoria, $tipo, $solicitante, $alunoAnterior) {
                    if ($user->id !== $solicitante?->id) {
                        $user->notify(new PreceptoriaNotification($preceptoria, $tipo, alunoAnterior: $alunoAnterior));
                    }
                });
            });
        }
    }

    public function form(Schema $schema): Schema
    {
        $user = auth()->user();

        return $schema
            ->components([
                Section::make('Selecionar Matrícula do Aluno')
                    ->schema([
                        Select::make('matricula_id')
                            ->label('Matrícula / Aluno')
                            ->options(function () {
                                return $this->getMatriculasAcessiveis()
                                    ->mapWithKeys(fn (Matricula $m) => [$m->id => $m->label_exibicao]);
                            })
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn () => $this->data['preceptoria_id'] = null),

                        // o ciclo pertence a um período letivo: matrícula de outro período não tem onde agendar
                        Placeholder::make('sem_ciclo_valido')
                            ->hiddenLabel()
                            ->visible(fn (Get $get) => filled($get('matricula_id'))
                                && ! app(PreceptoriaAgendaService::class)->ciclosParaAgendar((int) $get('matricula_id'))->exists())
                            ->content(function (Get $get) {
                                $periodo = Matricula::with('periodoLetivo')->find((int) $get('matricula_id'))?->periodoLetivo?->nome;
                                $dela = $periodo ? 'do período letivo <strong>'.e($periodo).'</strong>' : 'de uma turma sem período letivo';

                                return new HtmlString("
                                    <div class='p-3 bg-warning-50 border border-warning-200 rounded-lg dark:bg-warning-900/10 dark:border-warning-800'>
                                        <p class='text-sm text-warning-700 dark:text-warning-400'>
                                            Não é possível agendar preceptoria para esta matrícula: ela é {$dela} e não há ciclo de preceptoria válido (que ainda não tenha vencido) nesse período. Procure a secretaria.
                                        </p>
                                    </div>
                                ");
                            }),

                        Placeholder::make('agendamento_vigente')
                            ->label('Agendamento Vigente')
                            ->visible(function (Get $get) {
                                $mid = $get('matricula_id');
                                if (! $mid) {
                                    return false;
                                }

                                // Considerar agendamento vigente se houver uma preceptoria agendada no ciclo atual da matrícula
                                $cicloAtual = app(PreceptoriaAgendaService::class)->cicloAtualDaMatricula((int) $mid);
                                if (! $cicloAtual) {
                                    return false;
                                }

                                return Preceptoria::where('matricula_id', $mid)
                                    ->where('ciclo_preceptoria_id', $cicloAtual->id)
                                    ->exists();
                            })
                            ->content(function (Get $get) {
                                $mid = $get('matricula_id');
                                $cicloAtual = app(PreceptoriaAgendaService::class)->cicloAtualDaMatricula((int) $mid);

                                $p = Preceptoria::where('matricula_id', $mid)
                                    ->where('ciclo_preceptoria_id', $cicloAtual->id)
                                    ->with('professor')
                                    ->first();

                                if (! $p) {
                                    return '';
                                }

                                $dataF = Carbon::parse($p->data)->format('d/m/Y');
                                $horaF = Carbon::parse($p->hora_inicio)->format('H:i');

                                return new HtmlString("
                                    <div class='p-3 bg-primary-50 border border-primary-200 rounded-lg dark:bg-primary-900/10 dark:border-primary-800'>
                                        <p class='text-sm text-primary-700 dark:text-primary-400'>
                                            Este aluno já possui uma preceptoria agendada para <strong>{$dataF} às {$horaF}</strong> com o professor <strong>{$p->professor?->nome}</strong>.
                                        </p>
                                    </div>
                                ");
                            })
                            ->hintAction(
                                Action::make('cancelar_agendamento')
                                    ->label('Desagendar / Liberar Horário')
                                    ->icon('heroicon-m-x-circle')
                                    ->color('danger')
                                    ->requiresConfirmation()
                                    ->action(function (Get $get) {
                                        $mid = $get('matricula_id');
                                        $cicloAtual = app(PreceptoriaAgendaService::class)->cicloAtualDaMatricula((int) $mid);

                                        $p = Preceptoria::where('matricula_id', $mid)
                                            ->where('ciclo_preceptoria_id', $cicloAtual?->id)
                                            ->first();

                                        if ($p) {
                                            $this->liberarHorario($p->id);
                                        }
                                    })
                            ),
                    ])
                    ->columnSpanFull(),

                Section::make('Horários Disponíveis')
                    // Esconde sem matrícula, sem ciclo válido no período dela ou com agendamento no ciclo atual
                    ->visible(fn (Get $get) => filled($get('matricula_id'))
                        && app(PreceptoriaAgendaService::class)->podeAgendar((int) $get('matricula_id')))
                    ->schema([
                        Select::make('preceptoria_id')
                            ->label('Horário Disponível')
                            ->options(function (Get $get) use ($user) {
                                $matriculaId = $get('matricula_id');

                                if (! $matriculaId) {
                                    return [];
                                }

                                $roles = $user?->getRoleNames() ?? collect();
                                $isRestricted = $roles->isNotEmpty() && $roles->every(fn ($role) => in_array($role, ['responsavel', 'aluno']));
                                $minDate = $isRestricted ? now()->addDays(2)->toDateString() : now()->toDateString();

                                // só horários de professor da matrícula, em ciclo válido do período letivo dela
                                $query = app(PreceptoriaAgendaService::class)->horariosLivres((int) $matriculaId)
                                    ->where('data', '>=', $minDate)
                                    ->with('professor');

                                return $query->orderBy('data')
                                    ->orderBy('hora_inicio')
                                    ->get()
                                    ->mapWithKeys(function (Preceptoria $p) {
                                        $data = $p->data ? Carbon::parse($p->data)->format('d/m/Y') : '';
                                        $inicio = $p->hora_inicio ? Carbon::parse($p->hora_inicio)->format('H:i') : '';
                                        $fim = $p->hora_fim ? ' - '.Carbon::parse($p->hora_fim)->format('H:i') : '';

                                        return [
                                            $p->id => "{$p->professor?->nome} - {$data} às {$inicio}{$fim}",
                                        ];
                                    });
                            })
                            ->searchable()
                            ->required()
                            ->disabled(fn (Get $get) => ! $get('matricula_id')),
                    ])
                    ->columnSpanFull(),
            ])

            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Agendamento de Preceptoria')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
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
        $activeRole = session('active_role');

        $html = '<p>Nesta página você realiza o agendamento de uma preceptoria com um dos professores disponíveis.</p>';
        $html .= '<h3>Passo a Passo:</h3>';
        $html .= '<ol>';
        $html .= '<li><strong>Selecionar Matrícula:</strong> Escolha o aluno para o qual deseja agendar. ';

        if ($activeRole === 'responsavel') {
            $html .= 'Você verá apenas os seus dependentes.';
        } elseif ($activeRole === 'secretaria') {
            $html .= 'Você tem acesso a todos os alunos da instituição.';
        }

        $html .= '</li>';
        $html .= '<li><strong>Verificar Agendamento Vigente:</strong> Se o aluno já tiver uma preceptoria agendada para o ciclo atual, o sistema avisará e não permitirá um novo agendamento até que o atual seja realizado ou cancelado.</li>';
        $html .= '<li><strong>Escolher Horário:</strong> Selecione um dos horários disponíveis na lista. ';

        if ($activeRole === 'responsavel' || $activeRole === 'aluno') {
            $html .= '<br><em>Nota: Por regra de antecedência, você só pode agendar horários com no mínimo 2 dias de antecedência.</em>';
        }

        $html .= '</li>';
        $html .= '<li><strong>Confirmar:</strong> Clique no botão "Agendar Preceptoria" ao final da página para concluir.</li>';
        $html .= '</ol>';

        $html .= '<h3>Dúvidas Frequentes:</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Não vejo horários para meu filho:</strong> Os horários só aparecem se o professor conselheiro da turma, ou um professor designado para a matrícula do aluno, tiver liberado agendas futuras no sistema. Professores que apenas dão aula para a turma não entram. Além disso, o horário precisa ser de um ciclo de preceptoria do mesmo período letivo da matrícula do aluno e que ainda não tenha vencido; sem um ciclo assim, a tela avisa e não oferece horários.</li>';
        $html .= '<li><strong>Como cancelar?</strong> Se houver um agendamento vigente, aparecerá um botão vermelho "Desagendar / Liberar Horário" logo abaixo da seleção do aluno.</li>';
        $html .= '</ul>';

        return $html;
    }

    public function agendar(): void
    {
        $raw = $this->form->getState();

        // a lista já vem filtrada, mas o estado do formulário vem do navegador: confere o horário de novo
        $servico = app(PreceptoriaAgendaService::class);
        $matriculaId = (int) $raw['matricula_id'];

        if (! $servico->podeAgendar($matriculaId) || ! $servico->horariosLivres($matriculaId)->whereKey($raw['preceptoria_id'])->exists()) {
            Notification::make()
                ->title('Horário indisponível')
                ->body('Este horário não pode ser agendado para esta matrícula: confira o professor e se o ciclo de preceptoria é do período letivo dela.')
                ->danger()
                ->send();

            return;
        }

        try {
            $preceptoria = Preceptoria::findOrFail($raw['preceptoria_id']);

            $preceptoria->update([
                'matricula_id' => $raw['matricula_id'],
            ]);

            $preceptoria = $preceptoria->fresh(['professor.users', 'matricula.pessoa.responsaveis.users', 'matricula.pessoa.users']);

            $aluno = $preceptoria->matricula?->pessoa?->nome;

            app(PreceptoriaAgendaService::class)->registrarHistorico(
                $preceptoria,
                'agendamento_preceptoria',
                'Aluno agendado: '.($aluno ?? 'não informado'),
                ['antes' => null, 'depois' => $aluno],
            );

            $this->enviarNotificacoes($preceptoria, 'agendamento');

            Notification::make()
                ->title('Preceptoria agendada com sucesso!')
                ->success()
                ->send();

            $this->form->fill();

        } catch (ConflitoDeHorarioException $e) {
            Notification::make()
                ->title('Horário em conflito')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Erro ao agendar')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function mostrarBotaoAgendar(): bool
    {
        $mid = $this->data['matricula_id'] ?? null;

        return filled($mid) && app(PreceptoriaAgendaService::class)->podeAgendar((int) $mid);
    }
}

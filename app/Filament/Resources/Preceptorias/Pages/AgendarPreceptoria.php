<?php

namespace App\Filament\Resources\Preceptorias\Pages;

use App\Filament\Resources\Preceptorias\PreceptoriaResource;
use App\Models\CronogramaAula;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Preceptoria;
use App\Notifications\Preceptorias\PreceptoriaNotification;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
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

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }


    protected function getMatriculasAcessiveis(): Collection
    {
        $user = auth()->user();

        if ($user->hasRole(['super_admin', 'admin', 'secretaria'])) {
            return Matricula::with(['pessoa', 'turma', 'periodoLetivo'])->get();
        }

        $pessoasIds = $user->pessoas()->pluck('pessoa.id')->toArray();
        if (empty($pessoasIds)) {
            return collect();
        }

        $query = Matricula::query()->whereIn('pessoa_id', $pessoasIds);

        // Se o usuário for responsável, buscar também as matrículas dos alunos vinculados a ele
        if ($user->hasRole('responsavel')) {
            $query->orWhereHas('pessoa.responsaveis', function ($q) use ($pessoasIds) {
                $q->whereIn('responsavel_id', $pessoasIds);
            });
        }

        // Também mantemos a lógica de alunos() vinculados diretamente às pessoas do usuário
        foreach ($user->pessoas as $pessoa) {
            $alunosIds = $pessoa->alunos()->pluck('pessoa.id')->toArray();
            if (! empty($alunosIds)) {
                $query->orWhereIn('pessoa_id', $alunosIds);
            }
        }

        return $query->with(['pessoa', 'turma', 'periodoLetivo'])->get();
    }

    protected function getProfessoresDaMatricula(int $matriculaId): Collection
    {
        $matricula = Matricula::with(['turma.professorConselheiro', 'turma.cronogramasAula.professor'])->find($matriculaId);

        if (! $matricula || ! $matricula->turma) {
            return collect();
        }

        $professores = collect();

        if ($matricula->turma->professorConselheiro) {
            $professores->push($matricula->turma->professorConselheiro);
        }

        CronogramaAula::where('turma_id', $matricula->turma_id)
            ->whereNotNull('pessoa_id')
            ->with('professor')
            ->get()
            ->each(fn ($ca) => $ca->professor && $professores->push($ca->professor));

        return $professores->unique('id');
    }

    public function liberarHorario(int $id): void
    {
        $preceptoria = Preceptoria::with(['professor.users', 'matricula.pessoa.responsaveis.users'])->findOrFail($id);

        $this->enviarNotificacoes($preceptoria, 'liberacao');

        $preceptoria->update(['matricula_id' => null]);

        Notification::make()
            ->title('Agendamento cancelado com sucesso!')
            ->success()
            ->send();

        $this->form->fill(['matricula_id' => $this->data['matricula_id'] ?? null]);
    }

    protected function enviarNotificacoes(Preceptoria $preceptoria, string $tipo): void
    {
        // 1) Usuário que realizou o agendamento (Solicitante)
        $solicitante = auth()->user();
        if ($solicitante) {
            $solicitante->notify(new PreceptoriaNotification($preceptoria, $tipo, paraSolicitante: true));
        }

        // 2) Usuários relacionados com Pessoa Professor configurado para a Preceptoria escolhida
        if ($preceptoria->professor) {
            $preceptoria->professor->users->each(function ($user) use ($preceptoria, $tipo, $solicitante) {
                if ($user->id !== $solicitante?->id) {
                    $user->notify(new PreceptoriaNotification($preceptoria, $tipo));
                }
            });
        }

        // 3) Usuários vinculados a Pessoa Responsável e a Pessoa Aluno relacionados com a Matricula que foi configurada
        if ($preceptoria->matricula?->pessoa) {
            $alunoPessoa = $preceptoria->matricula->pessoa;

            // Notificar Usuários do Aluno (se houver)
            $alunoPessoa->users->each(function ($user) use ($preceptoria, $tipo, $solicitante) {
                if ($user->id !== $solicitante?->id) {
                    $user->notify(new PreceptoriaNotification($preceptoria, $tipo));
                }
            });

            // Notificar Usuários dos Responsáveis
            $alunoPessoa->responsaveis->each(function ($responsavel) use ($preceptoria, $tipo, $solicitante) {
                $responsavel->users->each(function ($user) use ($preceptoria, $tipo, $solicitante) {
                    if ($user->id !== $solicitante?->id) {
                        $user->notify(new PreceptoriaNotification($preceptoria, $tipo));
                    }
                });
            });
        }
    }

    public function form(Schema $schema): Schema
    {
        $user = auth()->user();
        $isAdminOrSecretaria = $user?->hasRole(['super_admin', 'admin', 'secretaria']);

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

                        Placeholder::make('agendamento_vigente')
                            ->label('Agendamento Vigente')
                            ->visible(function (Get $get) {
                                $mid = $get('matricula_id');
                                if (! $mid) {
                                    return false;
                                }

                                return Preceptoria::where('matricula_id', $mid)
                                    ->where('data', '>=', now()->toDateString())
                                    ->exists();
                            })
                            ->content(function (Get $get) {
                                $mid = $get('matricula_id');
                                $p = Preceptoria::where('matricula_id', $mid)
                                    ->where('data', '>=', now()->toDateString())
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
                                        $p = Preceptoria::where('matricula_id', $mid)
                                            ->where('data', '>=', now()->toDateString())
                                            ->first();

                                        if ($p) {
                                            $this->liberarHorario($p->id);
                                        }
                                    })
                            ),
                    ])
                    ->columnSpanFull(),

                Section::make('Horários Disponíveis')
                    ->visible(function (Get $get) {
                        $mid = $get('matricula_id');
                        if (! $mid) {
                            return false; // Esconde se não tiver matrícula
                        }

                        // Esconde se já houver agendamento
                        $temAgendamento = Preceptoria::where('matricula_id', $mid)
                            ->where('data', '>=', now()->toDateString())
                            ->exists();

                        return ! $temAgendamento;
                    })
                    ->schema([
                        Select::make('preceptoria_id')
                            ->label('Horário Disponível')
                            ->options(function (Get $get) use ($isAdminOrSecretaria) {
                                $matriculaId = $get('matricula_id');

                                if (! $matriculaId) {
                                    return [];
                                }

                                $query = Preceptoria::query()
                                    ->whereNull('matricula_id')
                                    ->where('data', '>=', now()->toDateString())
                                    ->with('professor');

                                if (! $isAdminOrSecretaria) {
                                    $professoresIds = $this->getProfessoresDaMatricula((int) $matriculaId)->pluck('id');
                                    $query->whereIn('professor_id', $professoresIds);
                                }

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

    public function agendar(): void
    {
        $raw = $this->form->getState();

        try {
            $preceptoria = Preceptoria::findOrFail($raw['preceptoria_id']);

            $preceptoria->update([
                'matricula_id' => $raw['matricula_id'],
            ]);

            $preceptoria = $preceptoria->fresh(['professor.users', 'matricula.pessoa.responsaveis.users', 'matricula.pessoa.users']);

            $this->enviarNotificacoes($preceptoria, 'agendamento');

            Notification::make()
                ->title('Preceptoria agendada com sucesso!')
                ->success()
                ->send();

            $this->form->fill();

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
        if (! $mid) {
            return false;
        }

        $temAgendamento = Preceptoria::where('matricula_id', $mid)
            ->where('data', '>=', now()->toDateString())
            ->exists();

        return ! $temAgendamento;
    }
}

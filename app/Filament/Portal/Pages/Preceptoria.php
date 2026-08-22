<?php

namespace App\Filament\Portal\Pages;

use App\Models\CicloPreceptoria;
use App\Models\CronogramaAula;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Preceptoria as PreceptoriaModel;
use App\Notifications\Preceptorias\PreceptoriaNotification;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use UnitEnum;

class Preceptoria extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static UnitEnum|string|null $navigationGroup = 'Meus Dados';

    protected static ?string $title = 'Agendar Preceptoria';

    protected static ?string $slug = 'preceptoria';

    protected string $view = 'filament.portal.pages.preceptoria';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    protected function getMatriculasAcessiveis(): Collection
    {
        $idsAcessiveis = auth()->user()->pessoasAcessiveis()->pluck('id');

        return Matricula::query()
            ->whereIn('pessoa_id', $idsAcessiveis)
            ->with(['pessoa', 'turma', 'periodoLetivo'])
            ->get();
    }

    protected function matriculaEhAcessivel(?int $matriculaId): bool
    {
        if (! $matriculaId) {
            return false;
        }

        return $this->getMatriculasAcessiveis()->contains('id', $matriculaId);
    }

    protected function getProfessoresDaMatricula(int $matriculaId): Collection
    {
        $matricula = Matricula::with(['turma'])->find($matriculaId);

        if (! $matricula || ! $matricula->turma) {
            return collect();
        }

        $professoresIds = collect();

        if ($matricula->turma->professor_conselheiro_id) {
            $professoresIds->push($matricula->turma->professor_conselheiro_id);
        }

        $cronogramaProfessoresIds = CronogramaAula::where('turma_id', $matricula->turma_id)
            ->whereNotNull('pessoa_id')
            ->pluck('pessoa_id');

        $professoresIds = $professoresIds->concat($cronogramaProfessoresIds)->unique();

        if ($professoresIds->isEmpty()) {
            return collect();
        }

        return Pessoa::whereIn('id', $professoresIds)->get();
    }

    public function liberarHorario(int $id): void
    {
        $preceptoria = PreceptoriaModel::with(['professor.users', 'matricula.pessoa.responsaveis.users'])->findOrFail($id);

        if (! $this->matriculaEhAcessivel($preceptoria->matricula_id)) {
            Notification::make()
                ->title('Você não tem permissão para esta ação.')
                ->danger()
                ->send();

            return;
        }

        $this->enviarNotificacoes($preceptoria, 'liberacao');

        $preceptoria->update(['matricula_id' => null]);

        Notification::make()
            ->title('Agendamento cancelado com sucesso!')
            ->success()
            ->send();

        $this->form->fill(['matricula_id' => $this->data['matricula_id'] ?? null]);
    }

    protected function enviarNotificacoes(PreceptoriaModel $preceptoria, string $tipo): void
    {
        $solicitante = auth()->user();
        if ($solicitante) {
            $solicitante->notify(new PreceptoriaNotification($preceptoria, $tipo, paraSolicitante: true));
        }

        if ($preceptoria->professor) {
            $preceptoria->professor->users->each(function ($user) use ($preceptoria, $tipo, $solicitante) {
                if ($user->id !== $solicitante?->id) {
                    $user->notify(new PreceptoriaNotification($preceptoria, $tipo));
                }
            });
        }

        if ($preceptoria->matricula?->pessoa) {
            $alunoPessoa = $preceptoria->matricula->pessoa;

            $alunoPessoa->users->each(function ($user) use ($preceptoria, $tipo, $solicitante) {
                if ($user->id !== $solicitante?->id) {
                    $user->notify(new PreceptoriaNotification($preceptoria, $tipo));
                }
            });

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
        return $schema
            ->components([
                Section::make('Selecionar Aluno')
                    ->schema([
                        Select::make('matricula_id')
                            ->label('Matrícula / Aluno')
                            ->options(fn () => $this->getMatriculasAcessiveis()
                                ->mapWithKeys(fn (Matricula $m) => [$m->id => $m->label_exibicao]))
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

                                $cicloAtual = CicloPreceptoria::vigentes()->first();
                                if (! $cicloAtual) {
                                    return false;
                                }

                                return PreceptoriaModel::where('matricula_id', $mid)
                                    ->where('ciclo_preceptoria_id', $cicloAtual->id)
                                    ->exists();
                            })
                            ->content(function (Get $get) {
                                $mid = $get('matricula_id');
                                $cicloAtual = CicloPreceptoria::vigentes()->first();

                                $p = PreceptoriaModel::where('matricula_id', $mid)
                                    ->where('ciclo_preceptoria_id', $cicloAtual?->id)
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
                                        $cicloAtual = CicloPreceptoria::vigentes()->first();

                                        $p = PreceptoriaModel::where('matricula_id', $mid)
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
                    ->description('Por regra de antecedência, só é possível agendar horários com no mínimo 2 dias de antecedência.')
                    ->visible(function (Get $get) {
                        $mid = $get('matricula_id');
                        if (! $mid) {
                            return false;
                        }

                        $cicloAtual = CicloPreceptoria::vigentes()->first();
                        if (! $cicloAtual) {
                            return true;
                        }

                        return ! PreceptoriaModel::where('matricula_id', $mid)
                            ->where('ciclo_preceptoria_id', $cicloAtual->id)
                            ->exists();
                    })
                    ->schema([
                        Select::make('preceptoria_id')
                            ->label('Horário Disponível')
                            ->options(function (Get $get) {
                                $matriculaId = $get('matricula_id');

                                if (! $matriculaId) {
                                    return [];
                                }

                                $minDate = now()->addDays(2)->toDateString();

                                $professoresIds = $this->getProfessoresDaMatricula((int) $matriculaId)->pluck('id');

                                return PreceptoriaModel::query()
                                    ->whereNull('matricula_id')
                                    ->where('data', '>=', $minDate)
                                    ->whereIn('professor_id', $professoresIds)
                                    ->with('professor')
                                    ->orderBy('data')
                                    ->orderBy('hora_inicio')
                                    ->get()
                                    ->mapWithKeys(function (PreceptoriaModel $p) {
                                        $data = $p->data ? Carbon::parse($p->data)->format('d/m/Y') : '';
                                        $inicio = $p->hora_inicio ? Carbon::parse($p->hora_inicio)->format('H:i') : '';
                                        $fim = $p->hora_fim ? ' - '.Carbon::parse($p->hora_fim)->format('H:i') : '';

                                        return [$p->id => "{$p->professor?->nome} - {$data} às {$inicio}{$fim}"];
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

        if (! $this->matriculaEhAcessivel((int) ($raw['matricula_id'] ?? null))) {
            Notification::make()
                ->title('Você não tem permissão para agendar para este aluno.')
                ->danger()
                ->send();

            return;
        }

        try {
            $preceptoria = PreceptoriaModel::findOrFail($raw['preceptoria_id']);

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

        $cicloAtual = CicloPreceptoria::vigentes()->first();
        if (! $cicloAtual) {
            return true;
        }

        return ! PreceptoriaModel::where('matricula_id', $mid)
            ->where('ciclo_preceptoria_id', $cicloAtual->id)
            ->exists();
    }
}

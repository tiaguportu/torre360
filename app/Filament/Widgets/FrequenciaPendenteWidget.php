<?php

namespace App\Filament\Widgets;

use App\Models\CronogramaAula;
use App\Models\FrequenciaEscolar;
use App\Models\Matricula;
use App\Traits\HasCustomWidgetShield;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class FrequenciaPendenteWidget extends Widget implements HasActions, HasForms
{
    use HasCustomWidgetShield;
    use InteractsWithActions;
    use InteractsWithForms;

    protected static ?int $sort = -4;

    protected string $view = 'filament.widgets.frequencia-pendente';

    protected int|string|array $columnSpan = 'full';

    /**
     * Retorna os cronogramas de aula com frequências pendentes agrupados por data (<= hoje).
     * Limita aos 3 últimos dias pendentes.
     */
    public function getPendenciasAgrupadas(): Collection
    {
        $user = Auth::user();
        if (! $user) {
            return collect();
        }

        $query = CronogramaAula::query()
            ->with(['turma.matriculas.pessoa', 'disciplina', 'professor'])
            ->whereDate('data', '<=', now()->toDateString())
            ->whereRaw('
                (SELECT COUNT(*) FROM matricula WHERE matricula.turma_id = cronograma_aula.turma_id AND (matricula.data_ativacao IS NULL OR matricula.data_ativacao <= cronograma_aula.data) AND (matricula.data_desativacao IS NULL OR matricula.data_desativacao > cronograma_aula.data)) > 
                (SELECT COUNT(*) FROM frequencia_escolar WHERE frequencia_escolar.cronograma_aula_id = cronograma_aula.id AND frequencia_escolar.situacao IS NOT NULL)
            ');

        // Se o usuário logado possui a role/papel ativo de professor, filtra apenas pelas aulas associadas a ele
        $isProfessor = $user->hasRole('professor')
            || session('active_role') === 'professor'
            || $user->active_role === 'professor';

        if ($isProfessor) {
            $pessoasIds = array_filter(array_merge(
                [$user->pessoa?->id],
                $user->pessoas ? $user->pessoas->pluck('id')->toArray() : []
            ));

            if (! empty($pessoasIds)) {
                $query->where(function ($q) use ($pessoasIds) {
                    $q->whereIn('pessoa_id', $pessoasIds)
                        ->orWhereHas('turma', function ($tq) use ($pessoasIds) {
                            $tq->whereIn('professor_conselheiro_id', $pessoasIds)
                                ->orWhereHas('disciplinas', function ($dq) use ($pessoasIds) {
                                    $dq->whereIn('turma_disciplina.professor_id', $pessoasIds);
                                });
                        });
                });
            } else {
                return collect();
            }
        }

        $cronogramas = $query->orderBy('data', 'desc')
            ->orderBy('hora_inicio', 'asc')
            ->get();

        return $cronogramas->groupBy(function (CronogramaAula $item) {
            return Carbon::parse($item->data)->format('Y-m-d');
        })->take(3);
    }

    /**
     * Action nativa do Filament para Lançamento de Frequência do Dia em Lote.
     */
    public function lancarChamadaDiaAction(): Action
    {
        return Action::make('lancarChamadaDia')
            ->label('Lançar Chamada do Dia')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->size('sm')
            ->modalHeading(fn (array $arguments): string => 'Lançamento de Frequência — '.Carbon::parse($arguments['data'] ?? now()->toDateString())->format('d/m/Y'))
            ->modalDescription('Selecione as matérias e defina a presença dos alunos matriculados.')
            ->modalSubmitActionLabel('Salvar Frequências')
            ->modalWidth('4xl')
            ->fillForm(function (array $arguments): array {
                $data = $arguments['data'] ?? now()->toDateString();
                $user = Auth::user();

                $query = CronogramaAula::query()
                    ->with(['turma.matriculas.pessoa', 'disciplina', 'professor'])
                    ->whereDate('data', $data)
                    ->whereRaw('
                        (SELECT COUNT(*) FROM matricula WHERE matricula.turma_id = cronograma_aula.turma_id AND (matricula.data_ativacao IS NULL OR matricula.data_ativacao <= cronograma_aula.data) AND (matricula.data_desativacao IS NULL OR matricula.data_desativacao > cronograma_aula.data)) > 
                        (SELECT COUNT(*) FROM frequencia_escolar WHERE frequencia_escolar.cronograma_aula_id = cronograma_aula.id AND frequencia_escolar.situacao IS NOT NULL)
                    ');

                $isProfessor = $user?->hasRole('professor')
                    || session('active_role') === 'professor'
                    || $user?->active_role === 'professor';

                if ($isProfessor && $user) {
                    $pessoasIds = array_filter(array_merge(
                        [$user->pessoa?->id],
                        $user->pessoas ? $user->pessoas->pluck('id')->toArray() : []
                    ));

                    if (! empty($pessoasIds)) {
                        $query->where(function ($q) use ($pessoasIds) {
                            $q->whereIn('pessoa_id', $pessoasIds)
                                ->orWhereHas('turma', function ($tq) use ($pessoasIds) {
                                    $tq->whereIn('professor_conselheiro_id', $pessoasIds)
                                        ->orWhereHas('disciplinas', function ($dq) use ($pessoasIds) {
                                            $dq->whereIn('turma_disciplina.professor_id', $pessoasIds);
                                        });
                                });
                        });
                    } else {
                        $query->whereRaw('1 = 0');
                    }
                }

                $cronogramas = $query->get();
                $turmaIds = $cronogramas->pluck('turma_id')->unique()->toArray();
                $aulasIds = $cronogramas->pluck('id')->toArray();

                $matriculas = Matricula::whereIn('turma_id', $turmaIds)
                    ->where(function ($q) use ($data) {
                        $q->whereNull('data_ativacao')
                            ->orWhereDate('data_ativacao', '<=', $data);
                    })
                    ->where(function ($q) use ($data) {
                        $q->whereNull('data_desativacao')
                            ->orWhereDate('data_desativacao', '>', $data);
                    })
                    ->with(['pessoa', 'turma'])
                    ->get()
                    ->sortBy(fn ($m) => $m->pessoa?->nome);

                $frequenciasForm = [];
                foreach ($matriculas as $m) {
                    $frequenciasForm[] = [
                        'matricula_id' => $m->id,
                        'situacao' => 'presente',
                    ];
                }

                return [
                    'data' => $data,
                    'aulas' => $aulasIds,
                    'frequencias' => $frequenciasForm,
                ];
            })
            ->form(function (array $arguments): array {
                $data = $arguments['data'] ?? now()->toDateString();
                $user = Auth::user();

                $query = CronogramaAula::query()
                    ->with(['turma', 'disciplina', 'professor'])
                    ->whereDate('data', $data)
                    ->whereRaw('
                        (SELECT COUNT(*) FROM matricula WHERE matricula.turma_id = cronograma_aula.turma_id AND (matricula.data_ativacao IS NULL OR matricula.data_ativacao <= cronograma_aula.data) AND (matricula.data_desativacao IS NULL OR matricula.data_desativacao > cronograma_aula.data)) > 
                        (SELECT COUNT(*) FROM frequencia_escolar WHERE frequencia_escolar.cronograma_aula_id = cronograma_aula.id AND frequencia_escolar.situacao IS NOT NULL)
                    ');

                $isProfessor = $user?->hasRole('professor')
                    || session('active_role') === 'professor'
                    || $user?->active_role === 'professor';

                if ($isProfessor && $user) {
                    $pessoasIds = array_filter(array_merge(
                        [$user->pessoa?->id],
                        $user->pessoas ? $user->pessoas->pluck('id')->toArray() : []
                    ));

                    if (! empty($pessoasIds)) {
                        $query->where(function ($q) use ($pessoasIds) {
                            $q->whereIn('pessoa_id', $pessoasIds)
                                ->orWhereHas('turma', function ($tq) use ($pessoasIds) {
                                    $tq->whereIn('professor_conselheiro_id', $pessoasIds)
                                        ->orWhereHas('disciplinas', function ($dq) use ($pessoasIds) {
                                            $dq->whereIn('turma_disciplina.professor_id', $pessoasIds);
                                        });
                                });
                        });
                    } else {
                        $query->whereRaw('1 = 0');
                    }
                }

                $cronogramas = $query->get();
                $turmaIds = $cronogramas->pluck('turma_id')->unique()->toArray();

                $optionsAulas = [];
                foreach ($cronogramas as $ca) {
                    $horario = ($ca->hora_inicio ? Carbon::parse($ca->hora_inicio)->format('H:i') : '').
                        ($ca->hora_fim ? ' - '.Carbon::parse($ca->hora_fim)->format('H:i') : '');

                    $optionsAulas[$ca->id] = "{$ca->disciplina?->nome} — Turma: {$ca->turma?->nome}".($horario ? " ({$horario})" : '');
                }

                $matriculas = Matricula::whereIn('turma_id', $turmaIds)
                    ->where(function ($q) use ($data) {
                        $q->whereNull('data_ativacao')
                            ->orWhereDate('data_ativacao', '<=', $data);
                    })
                    ->where(function ($q) use ($data) {
                        $q->whereNull('data_desativacao')
                            ->orWhereDate('data_desativacao', '>', $data);
                    })
                    ->with(['pessoa', 'turma', 'periodoLetivo'])
                    ->get()
                    ->sortBy(fn ($m) => $m->pessoa?->nome);

                $matriculasOptions = [];
                foreach ($matriculas as $m) {
                    $matriculasOptions[$m->id] = "{$m->pessoa?->nome} ({$m->turma?->nome})";
                }

                return [
                    Section::make('Disciplinas & Aulas do Dia')
                        ->description('Selecione as aulas onde deseja aplicar o lançamento de frequência.')
                        ->schema([
                            CheckboxList::make('aulas')
                                ->label('Aulas com Frequência Pendente')
                                ->options($optionsAulas)
                                ->bulkToggleable()
                                ->columns(2)
                                ->required(),
                        ]),

                    Section::make('Frequência dos Alunos (Chamada)')
                        ->description('Marque Presença ou Ausência para cada estudante matriculado nas turmas do dia.')
                        ->schema([
                            Repeater::make('frequencias')
                                ->label('Alunos Matriculados')
                                ->schema([
                                    Select::make('matricula_id')
                                        ->label('Aluno')
                                        ->options($matriculasOptions)
                                        ->required()
                                        ->disabled()
                                        ->dehydrated(),
                                    ToggleButtons::make('situacao')
                                        ->label('Situação')
                                        ->options([
                                            'presente' => 'Presente',
                                            'ausente' => 'Ausente',
                                        ])
                                        ->colors([
                                            'presente' => 'success',
                                            'ausente' => 'danger',
                                        ])
                                        ->icons([
                                            'presente' => 'heroicon-o-check',
                                            'ausente' => 'heroicon-o-x-circle',
                                        ])
                                        ->required()
                                        ->inline(),
                                ])
                                ->columns(2)
                                ->addable(false)
                                ->deletable(false)
                                ->reorderable(false),
                        ]),
                ];
            })
            ->action(function (array $data, array $arguments): void {
                $aulasAtivas = $data['aulas'] ?? [];

                if (empty($aulasAtivas)) {
                    Notification::make()
                        ->title('Nenhuma aula selecionada')
                        ->body('Por favor, selecione ao menos uma aula para lançar a frequência.')
                        ->warning()
                        ->send();

                    return;
                }

                $aulasObjetos = CronogramaAula::whereIn('id', $aulasAtivas)->get();
                $totalLancados = 0;

                foreach ($aulasObjetos as $ca) {
                    $matriculasTurma = Matricula::where('turma_id', $ca->turma_id)
                        ->where(function ($q) use ($ca) {
                            $q->whereNull('data_ativacao')
                                ->orWhereDate('data_ativacao', '<=', $ca->data);
                        })
                        ->where(function ($q) use ($ca) {
                            $q->whereNull('data_desativacao')
                                ->orWhereDate('data_desativacao', '>', $ca->data);
                        })
                        ->pluck('id')
                        ->toArray();

                    foreach ($data['frequencias'] as $frequenciaData) {
                        if (in_array($frequenciaData['matricula_id'], $matriculasTurma)) {
                            FrequenciaEscolar::updateOrCreate(
                                [
                                    'cronograma_aula_id' => $ca->id,
                                    'matricula_id' => $frequenciaData['matricula_id'],
                                ],
                                [
                                    'situacao' => $frequenciaData['situacao'] ?? 'presente',
                                ]
                            );
                            $totalLancados++;
                        }
                    }
                }

                $dataFormatada = Carbon::parse($arguments['data'] ?? now()->toDateString())->format('d/m/Y');

                Notification::make()
                    ->title('Frequências lançadas com sucesso!')
                    ->body("Foram registrados {$totalLancados} lançamentos de chamada para o dia {$dataFormatada}.")
                    ->success()
                    ->send();
            });
    }

    public static function canView(): bool
    {
        if (! static::hasWidgetShieldPermission()) {
            return false;
        }

        $user = Auth::user();
        if (! $user) {
            return false;
        }

        return (new static)->getPendenciasAgrupadas()->isNotEmpty();
    }
}

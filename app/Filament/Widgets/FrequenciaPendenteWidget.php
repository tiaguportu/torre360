<?php

namespace App\Filament\Widgets;

use App\Models\CronogramaAula;
use App\Models\FrequenciaEscolar;
use App\Models\Matricula;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class FrequenciaPendenteWidget extends Widget
{
    use HasWidgetShield;

    protected static ?int $sort = -4;

    protected string $view = 'filament.widgets.frequencia-pendente';

    protected int|string|array $columnSpan = 'full';

    public bool $showModal = false;

    public ?string $dataSelecionada = null;

    public ?string $dataSelecionadaFormatada = null;

    public array $aulasDoDia = [];

    public array $aulasSelecionadas = [];

    public array $alunosDoDia = [];

    /**
     * Retorna os cronogramas de aula com frequências pendentes agrupados por data (<= hoje).
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
                (SELECT COUNT(*) FROM matricula WHERE matricula.turma_id = cronograma_aula.turma_id) > 
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
                $query->whereIn('pessoa_id', $pessoasIds);
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

    public function getTotalPrevistoLancamentos(): int
    {
        $aulasAtivas = array_keys(array_filter($this->aulasSelecionadas));
        if (empty($aulasAtivas)) {
            return 0;
        }

        $aulasObjetos = CronogramaAula::whereIn('id', $aulasAtivas)->get();
        $turmaIdsAtivas = $aulasObjetos->pluck('turma_id')->toArray();

        $total = 0;
        foreach ($this->alunosDoDia as $alunoData) {
            if (! ($alunoData['selecionado'] ?? false)) {
                continue;
            }
            if (in_array($alunoData['turma_id'], $turmaIdsAtivas)) {
                $aulasDaTurma = $aulasObjetos->where('turma_id', $alunoData['turma_id'])->count();
                $total += $aulasDaTurma;
            }
        }

        return $total;
    }

    public function abrirModalLancamento(string $data): void
    {
        $this->dataSelecionada = $data;
        $this->dataSelecionadaFormatada = Carbon::parse($data)->format('d/m/Y');

        $user = Auth::user();
        $query = CronogramaAula::query()
            ->with(['turma.matriculas.pessoa', 'disciplina', 'professor'])
            ->whereDate('data', $data)
            ->whereRaw('
                (SELECT COUNT(*) FROM matricula WHERE matricula.turma_id = cronograma_aula.turma_id) > 
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
                $query->whereIn('pessoa_id', $pessoasIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $cronogramas = $query->get();

        $this->aulasDoDia = [];
        $this->aulasSelecionadas = [];

        $turmaIds = [];

        foreach ($cronogramas as $ca) {
            $this->aulasDoDia[] = [
                'id' => $ca->id,
                'turma_id' => $ca->turma_id,
                'turma_nome' => $ca->turma?->nome ?? 'Sem Turma',
                'disciplina_nome' => $ca->disciplina?->nome ?? 'Sem Disciplina',
                'professor_nome' => $ca->professor?->nome ?? 'Sem Professor',
                'horario' => ($ca->hora_inicio ? Carbon::parse($ca->hora_inicio)->format('H:i') : '').
                            ($ca->hora_fim ? ' - '.Carbon::parse($ca->hora_fim)->format('H:i') : ''),
            ];

            $this->aulasSelecionadas[$ca->id] = true;
            $turmaIds[] = $ca->turma_id;
        }

        $turmaIds = array_unique($turmaIds);

        $matriculas = Matricula::whereIn('turma_id', $turmaIds)
            ->with(['pessoa', 'turma'])
            ->get()
            ->sortBy(fn ($m) => $m->pessoa?->nome);

        $this->alunosDoDia = [];
        foreach ($matriculas as $m) {
            $this->alunosDoDia[$m->id] = [
                'id' => $m->id,
                'nome' => $m->pessoa?->nome ?? 'Aluno sem Nome',
                'turma_id' => $m->turma_id,
                'turma_nome' => $m->turma?->nome ?? '',
                'situacao' => 'presente',
                'selecionado' => true,
            ];
        }

        $this->showModal = true;
    }

    public function fecharModal(): void
    {
        $this->showModal = false;
        $this->dataSelecionada = null;
        $this->dataSelecionadaFormatada = null;
        $this->aulasDoDia = [];
        $this->aulasSelecionadas = [];
        $this->alunosDoDia = [];
    }

    public function marcarTodosAlunosPresentes(): void
    {
        foreach ($this->alunosDoDia as $id => $aluno) {
            $this->alunosDoDia[$id]['situacao'] = 'presente';
            $this->alunosDoDia[$id]['selecionado'] = true;
        }
    }

    public function marcarTodosAlunosAusentes(): void
    {
        foreach ($this->alunosDoDia as $id => $aluno) {
            $this->alunosDoDia[$id]['situacao'] = 'ausente';
            $this->alunosDoDia[$id]['selecionado'] = true;
        }
    }

    public function desselecionarTodosAlunos(): void
    {
        foreach ($this->alunosDoDia as $id => $aluno) {
            $this->alunosDoDia[$id]['selecionado'] = false;
        }
    }

    public function selecionarTodosAlunos(): void
    {
        foreach ($this->alunosDoDia as $id => $aluno) {
            $this->alunosDoDia[$id]['selecionado'] = true;
        }
    }

    public function toggleAula(int $aulaId): void
    {
        $this->aulasSelecionadas[$aulaId] = ! ($this->aulasSelecionadas[$aulaId] ?? false);
    }

    public function selecionarTodasAulas(): void
    {
        foreach ($this->aulasDoDia as $aula) {
            $this->aulasSelecionadas[$aula['id']] = true;
        }
    }

    public function desselecionarTodasAulas(): void
    {
        foreach ($this->aulasDoDia as $aula) {
            $this->aulasSelecionadas[$aula['id']] = false;
        }
    }

    public function salvarFrequenciasDoDia(): void
    {
        $aulasAtivas = array_keys(array_filter($this->aulasSelecionadas));

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
            // Pega alunos cuja turma bate com a turma da aula e que estão selecionados
            foreach ($this->alunosDoDia as $matriculaId => $alunoData) {
                if (! ($alunoData['selecionado'] ?? false)) {
                    continue;
                }

                if ($alunoData['turma_id'] == $ca->turma_id) {
                    FrequenciaEscolar::updateOrCreate(
                        [
                            'cronograma_aula_id' => $ca->id,
                            'matricula_id' => $matriculaId,
                        ],
                        [
                            'situacao' => $alunoData['situacao'] ?? 'presente',
                        ]
                    );

                    $totalLancados++;
                }
            }
        }

        Notification::make()
            ->title('Frequências lançadas com sucesso!')
            ->body("Foram registrados {$totalLancados} lançamentos de chamada para o dia {$this->dataSelecionadaFormatada}.")
            ->success()
            ->send();

        $this->fecharModal();
    }

    public static function canView(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        $activeRole = session('active_role') ?? $user->active_role;

        if (! $activeRole) {
            return false;
        }

        if ($activeRole !== 'super_admin') {
            try {
                $role = Role::findByName($activeRole, 'web');
                if (! $role->hasPermissionTo(static::getPermissionName())) {
                    return false;
                }
            } catch (\Throwable $e) {
                return false;
            }
        }

        return (new static)->getPendenciasAgrupadas()->isNotEmpty();
    }
}

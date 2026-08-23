<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Avaliacaos\AvaliacaoResource;
use App\Filament\Resources\CronogramaAulas\CronogramaAulaResource;
use App\Models\AlunoResponsavel;
use App\Models\Avaliacao;
use App\Models\Contrato;
use App\Models\CronogramaAula;
use App\Models\Disciplina;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Turma;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;

class CronogramaCalendarWidget extends Widget implements HasForms
{
    use HasWidgetShield;
    use InteractsWithForms;

    protected string $view = 'filament.widgets.cronograma-calendar-widget';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public ?array $data = [];

    // Filtros fixos vindos do componente pai (ex: página de edição)
    public ?int $fixedTurmaId = null;

    public ?int $fixedDisciplinaId = null;

    public ?int $fixedProfessorId = null;

    public function getAllEvents(): array
    {
        // 1. CronogramaAula
        $queryCronograma = CronogramaAula::with(['turma.serie.curso', 'disciplina', 'professor']);

        // Aplica filtros fixos e de permissão
        $this->applyQueryFilters($queryCronograma, 'pessoa_id');

        $user = auth()->user();
        $canViewAula = $user?->can('View:CronogramaAula') ?? false;
        $canViewAvaliacao = $user?->can('View:Avaliacao') ?? false;

        $eventsCronograma = $queryCronograma->get()->map(function (CronogramaAula $record) use ($canViewAula) {
            $start = $record->data->format('Y-m-d').'T'.($record->hora_inicio ? substr($record->hora_inicio, 0, 8) : '00:00:00');
            $end = $record->data->format('Y-m-d').'T'.($record->hora_fim ? substr($record->hora_fim, 0, 8) : '23:59:59');

            $turmaCor = $record->turma?->cor ?? '#10b981';
            $disciplinaCor = $record->disciplina?->cor ?? '#f59e0b';
            $cursoCor = $record->turma?->serie?->curso?->cor ?? '#7c3aed';

            return [
                'id' => (string) $record->id,
                'type' => 'aula',
                'title' => "{$record->turma?->nome} - {$record->disciplina?->nome}",
                'start' => $start,
                'end' => $end,
                'url' => $canViewAula ? CronogramaAulaResource::getUrl('view', ['record' => $record]) : null,
                'turma_id' => (string) $record->turma_id,
                'turma_nome' => $record->turma?->nome ?? 'Sem Turma',
                'turma_cor' => $turmaCor,
                'disciplina_id' => (string) $record->disciplina_id,
                'disciplina_nome' => $record->disciplina?->nome ?? 'Sem Disciplina',
                'disciplina_cor' => $disciplinaCor,
                'curso_nome' => $record->turma?->serie?->curso?->nome_interno ?? 'Sem Curso',
                'curso_cor' => $cursoCor,
                'professor_id' => (string) $record->pessoa_id,
                'professor_nome' => $record->professor?->nome ?? 'Sem Professor',
                'hora_inicio' => substr($record->hora_inicio ?? '', 0, 5),
                'hora_fim' => substr($record->hora_fim ?? '', 0, 5),
                'data' => date('d/m/Y', strtotime($record->data)),
                'conteudo_ministrado_full' => $record->conteudo_ministrado,
                'conteudo_ministrado' => str($record->conteudo_ministrado)->limit(100)->toString(),
                'backgroundColor' => $disciplinaCor,
                'borderColor' => $disciplinaCor,
                'textColor' => '#ffffff',
            ];
        });

        // 2. Avaliacao
        $queryAvaliacao = Avaliacao::with(['turma', 'disciplina', 'professor', 'etapaAvaliativa', 'categoria']);
        $this->applyQueryFilters($queryAvaliacao, 'professor_id');

        $eventsAvaliacao = $queryAvaliacao->get()->map(function (Avaliacao $record) use ($canViewAvaliacao) {
            $data = $record->data_prevista ? $record->data_prevista->format('Y-m-d') : date('Y-m-d');
            $start = $data.'T00:00:00';
            $end = $data.'T23:59:59';

            $turmaCor = $record->turma?->cor ?? '#10b981';
            $disciplinaCor = $record->disciplina?->cor ?? '#f59e0b';
            $cursoCor = $record->turma?->serie?->curso?->cor ?? '#7c3aed';

            return [
                'id' => 'aval-'.(string) $record->id,
                'type' => 'avaliacao',
                'title' => "AVALIAÇÃO: {$record->turma?->nome} - {$record->disciplina?->nome}",
                'start' => $start,
                'end' => $end,
                'url' => $canViewAvaliacao ? AvaliacaoResource::getUrl('view', ['record' => $record]) : null,
                'turma_id' => (string) $record->turma_id,
                'turma_nome' => $record->turma?->nome ?? 'Sem Turma',
                'turma_cor' => $turmaCor,
                'disciplina_id' => (string) $record->disciplina_id,
                'disciplina_nome' => $record->disciplina?->nome ?? 'Sem Disciplina',
                'disciplina_cor' => $disciplinaCor,
                'categoria_nome' => $record->categoria?->nome ?? 'Sem Categoria',
                'curso_nome' => $record->turma?->serie?->curso?->nome_interno ?? 'Avaliação',
                'curso_cor' => $cursoCor,
                'professor_id' => (string) $record->professor_id,
                'professor_nome' => $record->professor?->nome ?? 'Sem Professor',
                'hora_inicio' => 'Dia',
                'hora_fim' => 'Inteiro',
                'data' => $record->data_prevista ? $record->data_prevista->format('d/m/Y') : '',
                'conteudo_ministrado_full' => $record->etapaAvaliativa?->nome ?? 'Avaliação',
                'conteudo_ministrado' => $record->etapaAvaliativa?->nome ?? 'Avaliação',
                'backgroundColor' => '#facc15',
                'borderColor' => '#facc15',
                'textColor' => '#ffffff',
            ];
        });

        return array_merge($eventsCronograma->toArray(), $eventsAvaliacao->toArray());
    }

    private function applyQueryFilters($query, string $professorField): void
    {
        // Aplica filtros fixos se definidos
        if ($this->fixedTurmaId) {
            $query->where('turma_id', $this->fixedTurmaId);
        }
        if ($this->fixedDisciplinaId) {
            $query->where('disciplina_id', $this->fixedDisciplinaId);
        }
        if ($this->fixedProfessorId) {
            $query->where($professorField, $this->fixedProfessorId);
        }

        $user = auth()->user();
        if (! $user) {
            return;
        }

        $activeRole = session('active_role') ?? $user->active_role;

        if (in_array($activeRole, ['super_admin', 'secretaria', 'admin'])) {
            return;
        }

        if ($activeRole === 'professor') {
            $pessoaIds = $user->pessoas->pluck('id')->toArray();
            $query->whereIn($professorField, $pessoaIds);
        }

        if ($activeRole === 'responsavel' || $activeRole === 'aluno') {
            $turmasIds = $this->getTurmasPermitidasIds();
            $query->whereIn('turma_id', $turmasIds);
        }
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Filtros')
                    ->components([
                        Grid::make(3)
                            ->components([
                                Select::make('turmas')
                                    ->label('Turmas')
                                    ->multiple()
                                    ->options(function () {
                                        $query = Turma::whereNotNull('nome')->orderBy('nome');

                                        $activeRole = session('active_role') ?? auth()->user()?->active_role;
                                        if (in_array($activeRole, ['responsavel', 'aluno'])) {
                                            $query->whereIn('id', $this->getTurmasPermitidasIds());
                                        }

                                        return $query->pluck('nome', 'id');
                                    })
                                    ->searchable()
                                    ->live()
                                    ->hidden(fn () => $this->fixedTurmaId !== null),

                                Select::make('disciplinas')
                                    ->label('Disciplinas')
                                    ->multiple()
                                    ->options(function () {
                                        $query = Disciplina::whereNotNull('nome')->orderBy('nome');

                                        $activeRole = session('active_role') ?? auth()->user()?->active_role;
                                        if (in_array($activeRole, ['responsavel', 'aluno'])) {
                                            $turmasIds = $this->getTurmasPermitidasIds();
                                            $disciplinasCronograma = CronogramaAula::whereIn('turma_id', $turmasIds)->pluck('disciplina_id');
                                            $disciplinasAvaliacao = Avaliacao::whereIn('turma_id', $turmasIds)->pluck('disciplina_id');
                                            $todasDisciplinas = $disciplinasCronograma->merge($disciplinasAvaliacao)->filter()->unique();
                                            $query->whereIn('id', $todasDisciplinas);
                                        }

                                        return $query->pluck('nome', 'id');
                                    })
                                    ->searchable()
                                    ->live()
                                    ->hidden(fn () => $this->fixedDisciplinaId !== null),

                                Select::make('professores')
                                    ->label('Professores')
                                    ->multiple()
                                    ->options(function () {
                                        $query = Pessoa::whereHas('users', fn ($q) => $q->role('professor'))
                                            ->whereNotNull('nome')
                                            ->orderBy('nome');

                                        $activeRole = session('active_role') ?? auth()->user()?->active_role;
                                        if (in_array($activeRole, ['responsavel', 'aluno'])) {
                                            $turmasIds = $this->getTurmasPermitidasIds();
                                            $professoresCronogramaIds = CronogramaAula::whereIn('turma_id', $turmasIds)->pluck('pessoa_id');
                                            $professoresAvaliacaoIds = Avaliacao::whereIn('turma_id', $turmasIds)->pluck('professor_id');
                                            $todosProfessores = $professoresCronogramaIds->merge($professoresAvaliacaoIds)->filter()->unique();
                                            $query->whereIn('id', $todosProfessores);
                                        }

                                        return $query->pluck('nome', 'id');
                                    })
                                    ->searchable()
                                    ->live()
                                    ->hidden(fn () => $this->fixedProfessorId !== null || (session('active_role') ?? auth()->user()?->active_role) === 'professor'),
                            ]),
                    ])
                    ->collapsible()
                    ->compact()
                    ->hidden(fn () => $this->fixedTurmaId !== null &&
                        $this->fixedDisciplinaId !== null &&
                        $this->fixedProfessorId !== null
                    ),
            ])
            ->statePath('data');
    }

    private function getTurmasPermitidasIds(): array
    {
        $user = auth()->user();
        if (! $user) {
            return [];
        }

        $pessoaIds = $user->pessoas->pluck('id')->toArray();
        if (empty($pessoaIds)) {
            return [];
        }

        // 1. Alunos vinculados via tabela aluno_responsavel
        $alunoIds = AlunoResponsavel::whereIn('responsavel_id', $pessoaIds)
            ->pluck('aluno_id')
            ->toArray();

        // 2. Matrículas onde é Responsável Financeiro (via Contrato -> ResponsavelFinanceiro)
        $matriculasContratoIds = Contrato::whereHas('responsaveisFinanceiros', function ($q) use ($pessoaIds) {
            $q->whereIn('pessoa_id', $pessoaIds);
        })->pluck('matricula_id')->filter()->toArray();

        // 3. Matrículas das pessoas (próprio usuário ou alunos dependentes) ou via contrato
        $todasPessoasIds = array_unique(array_merge($pessoaIds, $alunoIds));

        $turmasIds = Matricula::query()
            ->where(function ($q) use ($todasPessoasIds, $matriculasContratoIds) {
                $q->whereIn('pessoa_id', $todasPessoasIds);
                if (! empty($matriculasContratoIds)) {
                    $q->orWhereIn('id', $matriculasContratoIds);
                }
            })
            ->pluck('turma_id')
            ->filter()
            ->unique()
            ->toArray();

        return array_values($turmasIds);
    }
}

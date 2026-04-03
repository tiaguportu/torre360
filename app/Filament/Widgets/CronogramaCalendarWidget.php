<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CronogramaAulas\CronogramaAulaResource;
use App\Models\CronogramaAula;
use App\Models\Disciplina;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Turma;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;

class CronogramaCalendarWidget extends Widget implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.widgets.cronograma-calendar-widget';

    protected int|string|array $columnSpan = 'full';

    public ?array $data = [];

    // Filtros fixos vindos do componente pai (ex: página de edição)
    public ?int $fixedTurmaId = null;

    public ?int $fixedDisciplinaId = null;

    public ?int $fixedProfessorId = null;

    public function getAllEvents(): array
    {
        $query = CronogramaAula::with(['turma.serie.curso', 'disciplina', 'professor']);

        // Aplica filtros fixos se definidos
        if ($this->fixedTurmaId) {
            $query->where('turma_id', $this->fixedTurmaId);
        }
        if ($this->fixedDisciplinaId) {
            $query->where('disciplina_id', $this->fixedDisciplinaId);
        }
        if ($this->fixedProfessorId) {
            $query->where('pessoa_id', $this->fixedProfessorId);
        }

        if (auth()->user()?->hasRole('professor')) {
            $query->where('pessoa_id', auth()->user()->pessoa?->id);
        }

        if (auth()->user()?->hasRole('responsavel')) {
            $turmasIds = $this->getTurmasPermitidasIds();
            $query->whereIn('turma_id', $turmasIds);
        }

        return $query->get()
            ->map(function (CronogramaAula $record) {
                // Formatação ISO 8601 completa para start e end
                $start = $record->data.'T'.($record->hora_inicio ? substr($record->hora_inicio, 0, 8) : '00:00:00');
                $end = $record->data.'T'.($record->hora_fim ? substr($record->hora_fim, 0, 8) : '23:59:59');

                $turmaCor = $record->turma?->cor ?? '#10b981';
                $disciplinaCor = $record->disciplina?->cor ?? '#f59e0b';
                $cursoCor = $record->turma?->serie?->curso?->cor ?? '#7c3aed';

                return [
                    'id' => (string) $record->id,
                    'title' => "{$record->turma?->nome} - {$record->disciplina?->nome}",
                    'start' => $start,
                    'end' => $end,
                    'url' => CronogramaAulaResource::getUrl('edit', ['record' => $record]),
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
            })
            ->toArray();
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

                                        if (auth()->user()?->hasRole('responsavel')) {
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
                                    ->options(Disciplina::whereNotNull('nome')->orderBy('nome')->pluck('nome', 'id'))
                                    ->searchable()
                                    ->live()
                                    ->hidden(fn () => $this->fixedDisciplinaId !== null),

                                Select::make('professores')
                                    ->label('Professores')
                                    ->multiple()
                                    ->options(Pessoa::whereHas('perfis', fn ($q) => $q->where('nome', 'Professor'))
                                        ->whereNotNull('nome')
                                        ->orderBy('nome')
                                        ->pluck('nome', 'id'))
                                    ->searchable()
                                    ->live()
                                    ->hidden(fn () => $this->fixedProfessorId !== null || auth()->user()?->hasRole('professor')),
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
        $pessoa = auth()->user()->pessoa;

        if (! $pessoa) {
            return [];
        }

        $contratosIds = $pessoa->responsaveisFinanceiros()->pluck('contrato_id');

        return Matricula::whereIn('contrato_id', $contratosIds)
            ->pluck('turma_id')
            ->unique()
            ->toArray();
    }
}

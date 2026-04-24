<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Preceptorias\PreceptoriaResource;
use App\Models\AlunoResponsavel;
use App\Models\CronogramaAula;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Preceptoria;
use App\Models\Turma;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;

class PreceptoriaCalendarWidget extends Widget implements HasForms
{
    use HasWidgetShield;
    use InteractsWithForms;

    protected string $view = 'filament.widgets.preceptoria-calendar-widget';

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    public ?array $data = [];

    public function getAllEvents(): array
    {
        $query = Preceptoria::with(['professor', 'matricula.pessoa']);

        $this->applyQueryFilters($query);

        return $query->get()->map(function (Preceptoria $record) {
            $dataStr = $record->data->format('Y-m-d');
            $inicioStr = $record->hora_inicio ? $record->hora_inicio->format('H:i:s') : '00:00:00';
            $fimStr = $record->hora_fim ? $record->hora_fim->format('H:i:s') : '23:59:59';

            $start = $dataStr.'T'.$inicioStr;
            $end = $dataStr.'T'.$fimStr;

            $isAgendado = $record->matricula_id !== null;
            $cor = $isAgendado ? '#10b981' : '#6b7280'; // Verde se agendado, Cinza se disponível

            return [
                'id' => (string) $record->id,
                'title' => $isAgendado
                    ? 'Agendado: '.($record->matricula?->pessoa?->nome ?? 'Aluno')
                    : 'Disponível',
                'start' => $start,
                'end' => $end,
                'url' => PreceptoriaResource::getUrl('edit', ['record' => $record]),
                'professor_id' => (string) $record->professor_id,
                'professor_nome' => $record->professor?->nome ?? 'Sem Professor',
                'matricula_id' => $record->matricula_id ? (string) $record->matricula_id : null,
                'aluno_nome' => $record->matricula?->pessoa?->nome ?? 'N/A',
                'hora_inicio' => $record->hora_inicio ? $record->hora_inicio->format('H:i') : '',
                'hora_fim' => $record->hora_fim ? $record->hora_fim->format('H:i') : '',
                'data' => date('d/m/Y', strtotime($record->data)),
                'backgroundColor' => $cor,
                'borderColor' => $cor,
                'textColor' => '#ffffff',
                'is_agendado' => $isAgendado,
            ];
        })->toArray();
    }

    private function applyQueryFilters(Builder $query): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        // Filtros da Interface (se houver dados preenchidos no formulário)
        if (! empty($this->data['status'])) {
            if ($this->data['status'] === 'agendado') {
                $query->whereNotNull('matricula_id');
            } elseif ($this->data['status'] === 'disponivel') {
                $query->whereNull('matricula_id');
            }
        }

        if (! empty($this->data['professores'])) {
            $query->whereIn('professor_id', $this->data['professores']);
        }

        // Filtros de Segurança por Role
        if ($user->hasRole('super_admin')) {
            return;
        }

        if ($user->hasRole('professor')) {
            $pessoaIds = $user->pessoas->pluck('id')->toArray();
            $query->whereIn('professor_id', $pessoaIds);
        }

        if ($user->hasRole('responsavel')) {
            $filterData = $this->getFilteredData();
            $matriculaIds = $filterData['matriculaIds'];
            $todosProfessoresIds = $filterData['professorIds'];

            $query->where(function ($q) use ($matriculaIds, $todosProfessoresIds) {
                // Preceptorias disponíveis dos professores vinculados (Regras 1 e 2)
                $q->whereNull('matricula_id')
                    ->whereIn('professor_id', $todosProfessoresIds);

                // Preceptorias agendadas para os alunos do responsável (Regra 3)
                $q->orWhereIn('matricula_id', $matriculaIds);
            });
        }
    }

    /**
     * @return array{matriculaIds: int[], professorIds: int[]}
     */
    private function getFilteredData(): array
    {
        $user = auth()->user();
        if (! $user) {
            return ['matriculaIds' => [], 'professorIds' => []];
        }

        // IDs das pessoas vinculadas ao usuário atual (Responsáveis)
        $responsavelPessoaIds = $user->pessoas->pluck('id')->toArray();

        // IDs dos alunos vinculados a esses responsáveis
        $alunoIds = AlunoResponsavel::whereIn('responsavel_id', $responsavelPessoaIds)
            ->pluck('aluno_id')
            ->toArray();

        // Matrículas ativas desses alunos
        $matriculas = Matricula::whereIn('pessoa_id', $alunoIds)
            ->where('situacao', 'ativa')
            ->get();

        $matriculaIds = $matriculas->pluck('id')->toArray();
        $turmaIds = $matriculas->pluck('turma_id')->unique()->toArray();

        // 1. Professores conselheiros das turmas dos alunos
        $professoresConselheirosIds = Turma::whereIn('id', $turmaIds)
            ->whereNotNull('professor_conselheiro_id')
            ->pluck('professor_conselheiro_id')
            ->toArray();

        // 2. Professores que dão aula (cronograma) para as turmas dos alunos
        $professoresCronogramaIds = CronogramaAula::whereIn('turma_id', $turmaIds)
            ->whereNotNull('pessoa_id')
            ->pluck('pessoa_id')
            ->unique()
            ->toArray();

        return [
            'matriculaIds' => $matriculaIds,
            'professorIds' => array_unique(array_merge($professoresConselheirosIds, $professoresCronogramaIds)),
        ];
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('📅 Calendário de Preceptorias')
                    ->components([
                        Grid::make(2)
                            ->components([
                                Select::make('professores')
                                    ->label('Professores')
                                    ->multiple()
                                    ->options(function () {
                                        $query = Pessoa::whereHas('users', fn ($q) => $q->role('professor'))
                                            ->whereNotNull('nome');

                                        if (auth()->user()?->hasRole('responsavel')) {
                                            $data = $this->getFilteredData();
                                            $query->whereIn('id', $data['professorIds']);
                                        }

                                        return $query->orderBy('nome')->pluck('nome', 'id');
                                    })
                                    ->searchable()
                                    ->live()
                                    ->hidden(fn () => auth()->user()?->hasRole('professor')),

                                Select::make('status')
                                    ->label('Status de Agendamento')
                                    ->options([
                                        'agendado' => 'Agendado (Com Matrícula)',
                                        'disponivel' => 'Disponível (Sem Matrícula)',
                                    ])
                                    ->placeholder('Todos')
                                    ->live(),
                            ]),
                    ])
                    ->collapsible()
                    ->compact(),
            ])
            ->statePath('data');
    }
}

<?php

namespace App\Filament\Resources\CronogramaAulas\Pages;

use App\Filament\Resources\CronogramaAulas\CronogramaAulaResource;
use App\Models\CronogramaAula;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;

class VerificaConflitos extends Page
{
    protected static string $resource = CronogramaAulaResource::class;

    protected string $view = 'filament.resources.cronograma-aulas.pages.verifica-conflitos';

    protected static ?string $title = 'Verificação de Conflitos';

    public function authorizeAccess(): void
    {
        $this->authorize('verificaConflitos');
    }

    public int $totalAlerts = 0;

    public int $turmaConflicts = 0;

    public int $profConflicts = 0;

    public function mount(): void
    {
        $this->turmaConflicts = count($this->getConflicts());
        $this->profConflicts = count($this->getProfessorConflicts());
        $this->totalAlerts = $this->turmaConflicts + $this->profConflicts;
    }

    public function deleteRecord(int $id): void
    {
        $record = CronogramaAula::find($id);

        if ($record) {
            $record->delete();

            Notification::make()
                ->title('Registro excluído')
                ->success()
                ->send();
        }
    }

    public function getConflicts(): array
    {
        return DB::table('cronograma_aula as a')
            ->join('cronograma_aula as b', function ($join) {
                $join->on('a.turma_id', '=', 'b.turma_id')
                    ->on('a.data', '=', 'b.data')
                    ->on('a.id', '<', 'b.id')
                    ->whereRaw('a.hora_inicio < b.hora_fim')
                    ->whereRaw('b.hora_inicio < a.hora_fim');
            })
            ->join('turma', 'a.turma_id', '=', 'turma.id')
            ->join('disciplina as d1', 'a.disciplina_id', '=', 'd1.id')
            ->join('disciplina as d2', 'b.disciplina_id', '=', 'd2.id')
            ->select([
                'a.id as a_id', 'a.hora_inicio as a_inicio', 'a.hora_fim as a_fim', 'd1.nome as a_disciplina',
                'b.id as b_id', 'b.hora_inicio as b_inicio', 'b.hora_fim as b_fim', 'd2.nome as b_disciplina',
                'turma.nome as turma_nome', 'a.data',
            ])
            ->get()->toArray();
    }

    public function getProfessorConflicts(): array
    {
        return DB::table('cronograma_aula as a')
            ->join('cronograma_aula as b', function ($join) {
                $join->on('a.pessoa_id', '=', 'b.pessoa_id')
                    ->on('a.data', '=', 'b.data')
                    ->on('a.id', '<', 'b.id')
                    ->whereRaw('a.hora_inicio < b.hora_fim')
                    ->whereRaw('b.hora_inicio < a.hora_fim');
            })
            ->join('pessoa', 'a.pessoa_id', '=', 'pessoa.id')
            ->join('turma as t1', 'a.turma_id', '=', 't1.id')
            ->join('turma as t2', 'b.turma_id', '=', 't2.id')
            ->join('disciplina as d1', 'a.disciplina_id', '=', 'd1.id')
            ->join('disciplina as d2', 'b.disciplina_id', '=', 'd2.id')
            ->select([
                'a.id as a_id', 'a.hora_inicio as a_inicio', 'a.hora_fim as a_fim', 'd1.nome as a_disciplina', 't1.nome as a_turma',
                'b.id as b_id', 'b.hora_inicio as b_inicio', 'b.hora_fim as b_fim', 'd2.nome as b_disciplina', 't2.nome as b_turma',
                'pessoa.nome as professor_nome', 'a.data',
            ])
            ->get()->toArray();
    }
}

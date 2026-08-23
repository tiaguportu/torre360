<?php

namespace App\Filament\Portal\Pages;

use App\Models\Avaliacao;
use App\Models\DiaNaoLetivo;
use App\Models\Matricula;
use App\Models\Turma;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class Calendario extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar';

    protected static UnitEnum|string|null $navigationGroup = 'Meus Dados';

    protected static ?string $title = 'Calendário';

    protected static ?string $slug = 'calendario';

    protected string $view = 'filament.portal.pages.calendario';

    protected function getTurmaIdsAcessiveis(): array
    {
        $idsAcessiveis = auth()->user()->pessoasAcessiveis()->pluck('id');

        return Matricula::whereIn('pessoa_id', $idsAcessiveis)
            ->pluck('turma_id')
            ->filter()
            ->unique()
            ->toArray();
    }

    public function getEvents(): array
    {
        $turmaIds = $this->getTurmaIdsAcessiveis();

        $provas = Avaliacao::whereIn('turma_id', $turmaIds)
            ->whereNotNull('data_prevista')
            ->with(['turma', 'disciplina'])
            ->get()
            ->map(fn (Avaliacao $a) => [
                'id' => 'aval-'.$a->id,
                'title' => "Prova: {$a->disciplina?->nome}",
                'start' => $a->data_prevista->format('Y-m-d'),
                'allDay' => true,
                'backgroundColor' => '#facc15',
                'borderColor' => '#facc15',
                'textColor' => '#1f2937',
                'extendedProps' => [
                    'tipo' => 'Prova',
                    'turma' => $a->turma?->nome,
                    'disciplina' => $a->disciplina?->nome,
                ],
            ]);

        $turmas = Turma::whereIn('id', $turmaIds)->with('serie.curso')->get();
        $periodoLetivoIds = $turmas->pluck('periodo_letivo_id')->filter()->unique();
        $cursoIds = $turmas->pluck('serie.curso.id')->filter()->unique();

        $feriados = DiaNaoLetivo::whereIn('periodo_letivo_id', $periodoLetivoIds)
            ->where('flag_ativo', true)
            ->where(fn ($q) => $q->whereNull('curso_id')->orWhereIn('curso_id', $cursoIds))
            ->get()
            ->map(fn (DiaNaoLetivo $d) => [
                'id' => 'feriado-'.$d->id,
                'title' => $d->descricao,
                'start' => $d->data,
                'allDay' => true,
                'backgroundColor' => '#ef4444',
                'borderColor' => '#ef4444',
                'textColor' => '#ffffff',
                'extendedProps' => ['tipo' => 'Não letivo'],
            ]);

        return $provas->concat($feriados)->values()->toArray();
    }
}

<?php

namespace App\Filament\Schemas\Components;

use App\Livewire\BoletimEtapaTable;
use App\Models\Avaliacao;
use App\Models\Disciplina;
use App\Models\EtapaAvaliativa;
use App\Models\Matricula;
use App\Models\Nota;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Collection;

class BoletimEdicaoGradesTable extends Component
{
    protected string $view = 'filament.schemas.components.boletim-edicao-grades-table';

    public static function make(): static
    {
        return app(static::class);
    }

    public function getMatricula(): ?Matricula
    {
        $record = $this->getRecord();
        if ($record instanceof Matricula) {
            return $record;
        }

        return $record?->matriculas?->first();
    }

    /**
     * Retorna as etapas para edição. Diferente da visualização, aqui podemos querer mostrar todas as etapas
     * ou apenas as que a escola permitir editar.
     */
    public function getEtapas(): Collection
    {
        $matricula = $this->getMatricula();
        if (! $matricula) {
            return collect();
        }

        // Pega todas as etapas que têm avaliações para a turma da matrícula
        $etapaIds = Avaliacao::query()
            ->where('turma_id', $matricula->turma_id)
            ->pluck('etapa_avaliativa_id')
            ->unique();

        return EtapaAvaliativa::whereIn('id', $etapaIds)->orderBy('id')->get();
    }

    public function getDadosParaEtapa(int $etapaId): array
    {
        $matricula = $this->getMatricula();
        $avaliacoes = Avaliacao::query()
            ->where('turma_id', $matricula->turma_id)
            ->where('etapa_avaliativa_id', $etapaId)
            ->with(['categoria', 'disciplina'])
            ->get();

        $categorias = $avaliacoes->map(fn ($av) => $av->categoria)->filter()->unique('id')->sortBy('ordem_boletim');
        $disciplinasIds = $avaliacoes->pluck('disciplina_id')->unique()->toArray();
        $disciplinas = Disciplina::whereIn('id', $disciplinasIds)
            ->orderBy('ordem_boletim')
            ->orderBy('nome')
            ->get();

        $notasAluno = $matricula->notas()->whereNotNull('valor')->get()->keyBy('avaliacao_id');
        $notasTurma = Nota::query()
            ->whereHas('matricula', fn ($q) => $q->where('turma_id', $matricula->turma_id))
            ->whereNotNull('valor')
            ->get()
            ->groupBy('avaliacao_id');

        $calc = new BoletimEtapaTable;

        $mediasAluno = [];
        $mediasTurma = [];

        foreach ($disciplinas as $disciplina) {
            $mediasAluno[$disciplina->id] = $calc->calcularMediaFinal($disciplina->id, $avaliacoes, $notasAluno);
            $mediasTurma[$disciplina->id] = $calc->getMediaTurmaEtapa($disciplina->id, $avaliacoes, $notasTurma);
        }

        return compact('avaliacoes', 'categorias', 'disciplinas', 'mediasAluno', 'mediasTurma');
    }
}

<?php

namespace App\Filament\Schemas\Components;

use App\Models\Avaliacao;
use App\Models\EtapaAvaliativa;
use App\Models\Matricula;
use App\Services\BoletimService;
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
        $boletimService = app(BoletimService::class);

        $dados = $boletimService->getDadosBoletim($matricula, $etapaId);
        $dadosEtapa = $dados['etapas'][0] ?? null;

        if (! $dadosEtapa) {
            return [
                'avaliacoes' => collect(),
                'categorias' => collect(),
                'disciplinas' => collect(),
                'mediasAluno' => [],
                'mediasTurma' => [],
            ];
        }

        $avaliacoes = Avaliacao::query()
            ->where('turma_id', $matricula->turma_id)
            ->where('etapa_avaliativa_id', $etapaId)
            ->with(['categoria', 'disciplina'])
            ->get();

        $categorias = $dadosEtapa['categorias'];
        $disciplinas = collect($dadosEtapa['linhas'])->pluck('disciplina');

        $mediasAluno = [];
        $mediasTurma = [];

        foreach ($dadosEtapa['linhas'] as $linha) {
            $disciplinaId = $linha['disciplina']->id;
            $mediasAluno[$disciplinaId] = $linha['media_final'];
            $mediasTurma[$disciplinaId] = $linha['media_turma'];
        }

        return compact('avaliacoes', 'categorias', 'disciplinas', 'mediasAluno', 'mediasTurma');
    }
}

<?php

namespace App\Filament\Portal\Pages;

use App\Filament\Portal\Concerns\InteractsWithMatriculaSelecionada;
use App\Models\Matricula;
use App\Models\NotaHabilidade;
use App\Services\BoletimService;
use App\Services\FrequenciaAlunoService;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Notas por disciplina e etapa avaliativa (mesmo cálculo do boletim), com
 * frequência por disciplina e, para turmas por habilidades, os conceitos BNCC.
 */
class Notas extends Page
{
    use InteractsWithMatriculaSelecionada;

    /**
     * Nota mínima de aprovação usada quando o período letivo não define uma.
     */
    private const NOTA_APROVACAO_PADRAO = 7.0;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static UnitEnum|string|null $navigationGroup = 'Meus Dados';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Notas';

    protected static ?string $slug = 'notas';

    protected string $view = 'filament.portal.pages.notas';

    public function mount(): void
    {
        $this->matriculaId = $this->getMatriculaSelecionada()?->id;
    }

    /**
     * @return array{matricula: ?Matricula, etapas: array<int, array<string, mixed>>, habilidades: Collection<string, Collection<int, NotaHabilidade>>, nota_aprovacao: float, frequencia_minima: float}
     */
    public function getDados(): array
    {
        $matricula = $this->getMatriculaSelecionada();

        if (! $matricula) {
            return [
                'matricula' => null,
                'etapas' => [],
                'habilidades' => collect(),
                'nota_aprovacao' => self::NOTA_APROVACAO_PADRAO,
                'frequencia_minima' => FrequenciaAlunoService::FREQUENCIA_MINIMA,
            ];
        }

        $boletim = app(BoletimService::class)->getDadosBoletim($matricula);

        $habilidades = NotaHabilidade::query()
            ->where('matricula_id', $matricula->id)
            ->with(['habilidade', 'avaliacaoHabilidade.etapaAvaliativa'])
            ->get()
            ->sortBy(fn (NotaHabilidade $nota): string => (string) $nota->habilidade?->codigo)
            ->groupBy(fn (NotaHabilidade $nota): string => $nota->avaliacaoHabilidade?->etapaAvaliativa?->nome ?? 'Sem etapa');

        return [
            'matricula' => $matricula,
            'etapas' => $boletim['etapas'],
            'habilidades' => $habilidades,
            'nota_aprovacao' => (float) ($matricula->periodoLetivo?->nota_aprovacao ?? self::NOTA_APROVACAO_PADRAO),
            'frequencia_minima' => FrequenciaAlunoService::FREQUENCIA_MINIMA,
        ];
    }
}

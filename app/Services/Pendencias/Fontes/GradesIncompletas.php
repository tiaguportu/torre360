<?php

declare(strict_types=1);

namespace App\Services\Pendencias\Fontes;

use App\Contracts\FontePendencia;
use App\Contracts\GuardaPendenciasEmCache;
use App\Filament\Pages\GradeHorariaAssistida;
use App\Models\PeriodoLetivo;
use App\Models\User;
use App\Services\GradeAssistidaService;
use App\Support\Pendencias\ItemPendencia;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Turmas do período letivo em curso cuja grade horária (Onda 64) ainda não cobre a matriz: faltam aulas ou o turno não
 * tem tempos cadastrados. O período é o mesmo que a tela da grade abre por padrão. Passado o início do período, a grade
 * incompleta já é atraso: as aulas acontecem sem horário fechado.
 *
 * A visão geral da grade consulta turma a turma, então o resultado fica guardado no cache por alguns minutos, para todos os
 * usuários (a contagem não depende de quem olha): sem isso, cada pessoa pagaria esse custo a cada vez que o selo do menu
 * é refeito.
 */
final class GradesIncompletas implements FontePendencia, GuardaPendenciasEmCache
{
    /** @var Collection<int, array{turma_id: int, nome: string, sem_tempos: bool, esperadas: int, faltam: int}>|null */
    private ?Collection $linhas = null;

    private ?PeriodoLetivo $periodo = null;

    private bool $periodoCarregado = false;

    public function chave(): string
    {
        return 'grades_incompletas';
    }

    public function titulo(): string
    {
        return 'Grades horárias incompletas';
    }

    public function area(): string
    {
        return 'Calendário e Horários';
    }

    public function icone(): string
    {
        return 'heroicon-o-table-cells';
    }

    public function orientacao(): string
    {
        return 'Abra a turma na Grade Horária Assistida, use a sugestão e aplique; o que sobrar vai a mão.';
    }

    public function permissao(): string
    {
        return 'GradeAssistida:Turma';
    }

    public function url(): string
    {
        return GradeHorariaAssistida::getUrl();
    }

    public function contar(User $usuario): int
    {
        return $this->incompletas()->count();
    }

    public function atrasados(User $usuario): int
    {
        return $this->periodoJaComecou() ? $this->incompletas()->count() : 0;
    }

    public function itens(User $usuario, int $limite): array
    {
        $atrasada = $this->periodoJaComecou();

        return $this->incompletas()
            ->take($limite)
            ->map(fn (array $linha): ItemPendencia => new ItemPendencia(
                titulo: $linha['nome'],
                detalhe: $linha['sem_tempos']
                    ? 'O turno da turma não tem tempos de aula cadastrados'
                    : "Faltam {$linha['faltam']} de {$linha['esperadas']} aulas da matriz na grade",
                url: GradeHorariaAssistida::getUrl(['turma' => $linha['turma_id']]),
                atrasado: $atrasada,
            ))
            ->values()
            ->all();
    }

    public function esquecer(): void
    {
        $periodo = $this->periodo();

        if ($periodo) {
            Cache::forget($this->chaveDoCache($periodo));
        }

        $this->linhas = null;
    }

    /**
     * As turmas da visão geral que ainda não estão completas, na ordem do nome.
     *
     * @return Collection<int, array{turma_id: int, nome: string, sem_tempos: bool, esperadas: int, faltam: int}>
     */
    private function incompletas(): Collection
    {
        if ($this->linhas === null) {
            $periodo = $this->periodo();

            $this->linhas = collect($periodo ? Cache::remember(
                $this->chaveDoCache($periodo),
                max(1, (int) config('mesa_pendencias.selo_cache_segundos', 300)),
                fn (): array => app(GradeAssistidaService::class)->visaoGeral($periodo)
                    ->filter(fn (array $linha): bool => $linha['sem_tempos'] || $linha['faltam'] > 0)
                    ->map(fn (array $linha): array => [
                        'turma_id' => (int) $linha['turma']->id,
                        'nome' => (string) $linha['turma']->nome,
                        'sem_tempos' => (bool) $linha['sem_tempos'],
                        'esperadas' => (int) $linha['esperadas'],
                        'faltam' => (int) $linha['faltam'],
                    ])
                    ->values()
                    ->all(),
            ) : []);
        }

        return $this->linhas;
    }

    private function chaveDoCache(PeriodoLetivo $periodo): string
    {
        return "mesa-pendencias:grades-incompletas:{$periodo->id}";
    }

    /**
     * O mesmo período que a tela da grade abre por padrão: o mais recente que já começou (ou o mais recente de todos).
     */
    private function periodo(): ?PeriodoLetivo
    {
        if (! $this->periodoCarregado) {
            $this->periodo = PeriodoLetivo::query()->whereDate('data_inicio', '<=', today())->orderByDesc('data_inicio')->first()
                ?? PeriodoLetivo::query()->orderByDesc('data_inicio')->first();
            $this->periodoCarregado = true;
        }

        return $this->periodo;
    }

    private function periodoJaComecou(): bool
    {
        return (bool) $this->periodo()?->data_inicio?->lte(today());
    }
}

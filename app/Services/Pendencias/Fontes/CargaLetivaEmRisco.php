<?php

declare(strict_types=1);

namespace App\Services\Pendencias\Fontes;

use App\Contracts\FontePendencia;
use App\Contracts\GuardaPendenciasEmCache;
use App\Enums\SituacaoCargaLetiva;
use App\Filament\Pages\CargaLetiva;
use App\Models\PeriodoLetivo;
use App\Models\User;
use App\Services\CargaLetivaService;
use App\Support\Pendencias\ItemPendencia;
use App\Support\Pendencias\PeriodoEmCurso;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Turmas do período em curso cuja carga letiva (Onda 49) está em risco ou insuficiente contra o mínimo da LDB. O resultado
 * fica guardado alguns minutos para todos os usuários, porque a apuração consulta turma a turma.
 */
final class CargaLetivaEmRisco implements FontePendencia, GuardaPendenciasEmCache
{
    /** @var Collection<int, array{turma_id: int, nome: string, situacao: string, detalhe: string}>|null */
    private ?Collection $linhas = null;

    private ?PeriodoLetivo $periodo = null;

    private bool $periodoCarregado = false;

    public function chave(): string
    {
        return 'carga_letiva_em_risco';
    }

    public function titulo(): string
    {
        return 'Carga letiva em risco';
    }

    public function area(): string
    {
        return 'Calendário e Horários';
    }

    public function icone(): string
    {
        return 'heroicon-o-scale';
    }

    public function orientacao(): string
    {
        return 'Abra a turma: lance a chamada das aulas sem registro, marque as que não aconteceram e agende a reposição.';
    }

    public function permissao(): string
    {
        return 'CargaLetiva:Turma';
    }

    public function url(): string
    {
        return CargaLetiva::getUrl();
    }

    public function contar(User $usuario): int
    {
        return $this->emRisco()->count();
    }

    /**
     * O calendário que nem fecha o mínimo (insuficiente) já é atraso.
     */
    public function atrasados(User $usuario): int
    {
        return $this->emRisco()->where('situacao', SituacaoCargaLetiva::Insuficiente->value)->count();
    }

    public function itens(User $usuario, int $limite): array
    {
        return $this->emRisco()
            ->sortByDesc(fn (array $linha): int => $linha['situacao'] === SituacaoCargaLetiva::Insuficiente->value ? 1 : 0)
            ->take($limite)
            ->map(fn (array $linha): ItemPendencia => new ItemPendencia(
                titulo: $linha['nome'],
                detalhe: $linha['detalhe'],
                url: CargaLetiva::getUrl(['periodo' => $this->periodo()?->id, 'turma' => $linha['turma_id']]),
                atrasado: $linha['situacao'] === SituacaoCargaLetiva::Insuficiente->value,
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
     * @return Collection<int, array{turma_id: int, nome: string, situacao: string, detalhe: string}>
     */
    private function emRisco(): Collection
    {
        if ($this->linhas === null) {
            $periodo = $this->periodo();

            $this->linhas = collect($periodo ? Cache::remember(
                $this->chaveDoCache($periodo),
                max(1, (int) config('mesa_pendencias.selo_cache_segundos', 300)),
                fn (): array => app(CargaLetivaService::class)->apurarPeriodo($periodo)
                    ->filter(fn (array $a): bool => in_array($a['situacao'], [SituacaoCargaLetiva::EmRisco, SituacaoCargaLetiva::Insuficiente], true))
                    ->map(fn (array $a): array => [
                        'turma_id' => (int) $a['turma']->id,
                        'nome' => (string) $a['turma']->nome,
                        'situacao' => $a['situacao']->value,
                        'detalhe' => $a['situacao']->getLabel().': faltam '.$a['dias_faltantes'].' dia(s) e '.number_format($a['horas_faltantes'], 1, ',', '.').' h para o mínimo',
                    ])
                    ->values()
                    ->all(),
            ) : []);
        }

        return $this->linhas;
    }

    private function chaveDoCache(PeriodoLetivo $periodo): string
    {
        return "mesa-pendencias:carga-letiva-em-risco:{$periodo->id}";
    }

    private function periodo(): ?PeriodoLetivo
    {
        if (! $this->periodoCarregado) {
            $this->periodo = PeriodoEmCurso::atual();
            $this->periodoCarregado = true;
        }

        return $this->periodo;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Pendencias\Fontes;

use App\Contracts\FontePendencia;
use App\Filament\Pages\RentabilidadeRealizada;
use App\Models\User;
use App\Services\RentabilidadeTurmaService;
use App\Support\Pendencias\ItemPendencia;
use Illuminate\Support\Facades\Cache;

/**
 * Turmas com resultado realizado negativo (e receita) em todos os últimos meses completos (Onda 77). A turma pequena de uma conversa
 * de impressão vira um número. Fica atrasada a que perdeu mais que a margem crítica da configuração no último mês.
 *
 * O cálculo percorre vários meses, por isso é guardado em cache por algumas horas.
 */
final class TurmasComResultadoNegativo implements FontePendencia
{
    /** @var list<array{turma_id: int, turma: string, resultados: list<float>, margens: list<?float>}>|null */
    private ?array $turmas = null;

    public function chave(): string
    {
        return 'turmas_com_resultado_negativo';
    }

    public function titulo(): string
    {
        return 'Turmas com resultado negativo';
    }

    public function area(): string
    {
        return 'Financeiro';
    }

    public function icone(): string
    {
        return 'heroicon-o-chart-bar-square';
    }

    public function orientacao(): string
    {
        return 'Abra o resultado realizado, veja o que pesa na turma (docente, direto ou rateio) e decida entre ajustar preço, turma ou custo.';
    }

    public function permissao(): string
    {
        return 'View:RentabilidadeRealizada';
    }

    public function url(): string
    {
        return RentabilidadeRealizada::getUrl();
    }

    /**
     * @return list<array{turma_id: int, turma: string, resultados: list<float>, margens: list<?float>}>
     */
    private function turmas(): array
    {
        $meses = max(1, (int) config('financeiro.rentabilidade.meses_alerta', 3));
        $criterio = (string) config('financeiro.rentabilidade.criterio_padrao', 'aluno');

        return $this->turmas ??= Cache::remember('mesa.turmas_resultado_negativo.'.today()->format('Y-m').".{$meses}.{$criterio}", now()->addHours(6), fn (): array => app(RentabilidadeTurmaService::class)->comResultadoNegativoHa($meses, null, $criterio));
    }

    /**
     * @param  array{margens: list<?float>}  $turma
     */
    private function critica(array $turma): bool
    {
        $margem = $turma['margens'][0] ?? null;

        return $margem !== null && $margem <= (float) config('financeiro.rentabilidade.margem_critica', -20.0);
    }

    public function contar(User $usuario): int
    {
        return count($this->turmas());
    }

    public function atrasados(User $usuario): int
    {
        return count(array_filter($this->turmas(), fn (array $t): bool => $this->critica($t)));
    }

    public function itens(User $usuario, int $limite): array
    {
        return collect($this->turmas())
            ->sortBy(fn (array $t): float => (float) array_sum($t['resultados']))
            ->take($limite)
            ->map(fn (array $t): ItemPendencia => new ItemPendencia(
                titulo: $t['turma'],
                detalhe: 'Negativa há '.count($t['resultados']).' mês(es): '.implode(' · ', array_map(fn (float $v): string => '-R$ '.number_format(abs($v), 2, ',', '.'), $t['resultados'])),
                url: RentabilidadeRealizada::getUrl(),
                atrasado: $this->critica($t),
            ))
            ->values()
            ->all();
    }
}

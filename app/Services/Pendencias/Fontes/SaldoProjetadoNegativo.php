<?php

declare(strict_types=1);

namespace App\Services\Pendencias\Fontes;

use App\Contracts\FontePendencia;
use App\Filament\Pages\FluxoProjetado;
use App\Models\User;
use App\Services\FluxoProjetadoService;
use App\Support\Pendencias\ItemPendencia;

/**
 * Caixa projetado negativo nos próximos dias (Onda 78), no cenário da média histórica de recebimento. É uma linha só: o dia em que
 * o saldo fica negativo. Dentro do prazo "urgente" da configuração a linha passa a atrasada.
 */
final class SaldoProjetadoNegativo implements FontePendencia
{
    /** @var array<string, mixed>|null */
    private ?array $projecao = null;

    public function chave(): string
    {
        return 'saldo_projetado_negativo';
    }

    public function titulo(): string
    {
        return 'Caixa projetado negativo';
    }

    public function area(): string
    {
        return 'Financeiro';
    }

    public function icone(): string
    {
        return 'heroicon-o-presentation-chart-line';
    }

    public function orientacao(): string
    {
        return 'Abra o fluxo projetado, veja o que pesa na semana e antecipe recebimentos ou renegocie pagamentos antes da data.';
    }

    public function permissao(): string
    {
        return 'View:FluxoProjetado';
    }

    public function url(): string
    {
        return FluxoProjetado::getUrl();
    }

    /**
     * @return array<string, mixed>
     */
    private function projecao(): array
    {
        return $this->projecao ??= app(FluxoProjetadoService::class)->projetar(null, max(7, (int) config('financeiro.projecao.alerta_dias', 30)), 'esperado');
    }

    public function contar(User $usuario): int
    {
        return $this->projecao()['primeira_data_negativa'] !== null ? 1 : 0;
    }

    public function atrasados(User $usuario): int
    {
        $data = $this->projecao()['primeira_data_negativa'];

        return $data !== null && $data->lte(today()->addDays((int) config('financeiro.projecao.urgente_dias', 14))) ? 1 : 0;
    }

    public function itens(User $usuario, int $limite): array
    {
        $p = $this->projecao();
        $data = $p['primeira_data_negativa'];

        if ($data === null) {
            return [];
        }

        return [new ItemPendencia(
            titulo: 'O saldo fica negativo em '.$data->format('d/m/Y'),
            detalhe: 'Menor saldo previsto R$ '.number_format((float) $p['menor_saldo'], 2, ',', '.').' em '.$p['data_menor_saldo']->format('d/m/Y').' · cenário "'.$p['cenario_nome'].'"',
            url: FluxoProjetado::getUrl(),
            atrasado: $this->atrasados($usuario) > 0,
        )];
    }
}

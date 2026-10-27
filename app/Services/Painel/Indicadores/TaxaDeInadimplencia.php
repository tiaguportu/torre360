<?php

declare(strict_types=1);

namespace App\Services\Painel\Indicadores;

use App\Enums\StatusFatura;
use App\Filament\Pages\RelatorioInadimplencia;
use App\Models\Fatura;
use App\Support\Painel\ContextoPainel;

final class TaxaDeInadimplencia extends IndicadorBase
{
    public const JANELA_DIAS = 90;

    public function chave(): string
    {
        return 'taxa_de_inadimplencia';
    }

    public function titulo(): string
    {
        return 'Taxa de inadimplência (90 dias)';
    }

    public function grupo(): string
    {
        return 'Financeiro';
    }

    public function formato(): string
    {
        return 'percentual';
    }

    public function melhor(): string
    {
        return 'menor';
    }

    public function permissao(): string
    {
        return 'View:RelatorioInadimplencia';
    }

    public function descricao(): string
    {
        return 'Do que venceu nos últimos 90 dias (faturas não canceladas), quanto continua sem pagamento.';
    }

    public function url(): ?string
    {
        return RelatorioInadimplencia::getUrl();
    }

    public function valor(ContextoPainel $contexto): ?float
    {
        $faturas = Fatura::query()
            ->where('status', '!=', StatusFatura::Cancelado->value)
            ->whereDate('vencimento', '>=', $contexto->hoje->subDays(self::JANELA_DIAS)->toDateString())
            ->whereDate('vencimento', '<', $contexto->hoje->toDateString())
            ->with(['itens', 'transacoes'])
            ->get();

        $faturado = (float) $faturas->sum('valor');

        if ($faturado <= 0) {
            return null;
        }

        $aberto = (float) $faturas->sum(fn (Fatura $f): float => max(0.0, (float) $f->valor_restante));

        return round($aberto / $faturado * 100, 1);
    }
}

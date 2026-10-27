<?php

declare(strict_types=1);

namespace App\Services\Painel\Indicadores;

use App\Filament\Pages\OrcadoRealizado;
use App\Models\Orcamento;
use App\Services\OrcamentoService;
use App\Support\Painel\ContextoPainel;

final class DespesasNoOrcamento extends IndicadorBase
{
    public function chave(): string
    {
        return 'despesas_no_orcamento';
    }

    public function titulo(): string
    {
        return 'Despesas sobre o orçamento';
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
        return 'View:OrcadoRealizado';
    }

    public function descricao(): string
    {
        return 'Despesas realizadas no ano até o mês corrente sobre o previsto no orçamento aprovado até o mesmo mês (100% é gastar exatamente o previsto).';
    }

    public function url(): ?string
    {
        return OrcadoRealizado::getUrl();
    }

    public function valor(ContextoPainel $contexto): ?float
    {
        $orcamento = Orcamento::vigenteDoAno($contexto->hoje->year);

        if (! $orcamento) {
            return null;
        }

        $previsto = 0.0;
        $realizado = 0.0;

        foreach (app(OrcamentoService::class)->comparativo($orcamento, $contexto->hoje->month) as $linha) {
            if ($linha['tipo'] === 'despesa') {
                $previsto += $linha['previsto'];
                $realizado += $linha['realizado'];
            }
        }

        return $previsto > 0 ? round($realizado / $previsto * 100, 1) : null;
    }
}

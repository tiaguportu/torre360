<?php

declare(strict_types=1);

namespace App\Services\Painel\Indicadores;

use App\Filament\Pages\RelatorioDRE;
use App\Services\DREService;
use App\Support\Painel\ContextoPainel;

final class ResultadoDoMesAnterior extends IndicadorBase
{
    public function chave(): string
    {
        return 'resultado_do_mes_anterior';
    }

    public function titulo(): string
    {
        return 'Resultado do mês anterior';
    }

    public function grupo(): string
    {
        return 'Financeiro';
    }

    public function formato(): string
    {
        return 'dinheiro';
    }

    public function melhor(): string
    {
        return 'maior';
    }

    public function permissao(): string
    {
        return 'View:RelatorioDRE';
    }

    public function descricao(): string
    {
        return 'Receitas menos despesas do último mês completo, exatamente como o DRE calcula (transações bancárias classificadas).';
    }

    public function url(): ?string
    {
        return RelatorioDRE::getUrl();
    }

    public function valor(ContextoPainel $contexto): ?float
    {
        $mes = $contexto->hoje->startOfMonth()->subMonthNoOverflow();
        $dre = app(DREService::class)->generate($mes->toDateString(), $mes->endOfMonth()->toDateString());

        return round((float) $dre['resumo']['resultado_liquido'], 2);
    }
}

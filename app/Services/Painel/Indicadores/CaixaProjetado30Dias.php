<?php

declare(strict_types=1);

namespace App\Services\Painel\Indicadores;

use App\Filament\Pages\FluxoProjetado;
use App\Services\FluxoProjetadoService;
use App\Support\Painel\ContextoPainel;

final class CaixaProjetado30Dias extends IndicadorBase
{
    public function chave(): string
    {
        return 'caixa_projetado_30_dias';
    }

    public function titulo(): string
    {
        return 'Caixa projetado em 30 dias';
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
        return 'View:FluxoProjetado';
    }

    public function descricao(): string
    {
        return 'Saldo previsto daqui a 30 dias no cenário da média histórica de recebimento (é estimativa; vale o que o fluxo projetado diz).';
    }

    public function url(): ?string
    {
        return FluxoProjetado::getUrl();
    }

    public function valor(ContextoPainel $contexto): ?float
    {
        return app(FluxoProjetadoService::class)->projetar($contexto->hoje, 30, 'esperado')['saldo_final'];
    }
}

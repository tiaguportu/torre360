<?php

declare(strict_types=1);

namespace App\Services\Painel\Indicadores;

use App\Filament\Resources\Interessados\InteressadoResource;
use App\Models\Interessado;
use App\Support\Painel\ContextoPainel;

final class LeadsNovos30Dias extends IndicadorBase
{
    public function chave(): string
    {
        return 'leads_novos_30_dias';
    }

    public function titulo(): string
    {
        return 'Leads novos (30 dias)';
    }

    public function grupo(): string
    {
        return 'Captação';
    }

    public function formato(): string
    {
        return 'inteiro';
    }

    public function melhor(): string
    {
        return 'maior';
    }

    public function permissao(): string
    {
        return 'ViewAny:Interessado';
    }

    public function descricao(): string
    {
        return 'Interessados cadastrados nos últimos 30 dias, de qualquer origem.';
    }

    public function url(): ?string
    {
        return InteressadoResource::getUrl('index');
    }

    public function valor(ContextoPainel $contexto): ?float
    {
        return (float) Interessado::query()->where('created_at', '>=', $contexto->hoje->subDays(30)->startOfDay())->count();
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Painel\Indicadores;

use App\Filament\Resources\Interessados\InteressadoResource;
use App\Models\Interessado;
use App\Support\Painel\ContextoPainel;

final class ConversaoDeLeads90Dias extends IndicadorBase
{
    public function chave(): string
    {
        return 'conversao_de_leads_90_dias';
    }

    public function titulo(): string
    {
        return 'Conversão de leads (90 dias)';
    }

    public function grupo(): string
    {
        return 'Captação';
    }

    public function formato(): string
    {
        return 'percentual';
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
        return 'Dos interessados cadastrados nos últimos 90 dias, quantos já estão em etapa de ganho (matriculados).';
    }

    public function url(): ?string
    {
        return InteressadoResource::getUrl('index');
    }

    public function valor(ContextoPainel $contexto): ?float
    {
        $leads = Interessado::query()->where('created_at', '>=', $contexto->hoje->subDays(90)->startOfDay());
        $total = (clone $leads)->count();

        if ($total === 0) {
            return null;
        }

        return round((clone $leads)->ganhos()->count() / $total * 100, 1);
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Painel\Indicadores;

use App\Models\FrequenciaEscolar;
use App\Support\Painel\ContextoPainel;

final class FrequenciaMedia30Dias extends IndicadorBase
{
    public function chave(): string
    {
        return 'frequencia_media_30_dias';
    }

    public function titulo(): string
    {
        return 'Frequência média (30 dias)';
    }

    public function grupo(): string
    {
        return 'Pedagógico';
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
        return 'ViewAny:FrequenciaEscolar';
    }

    public function descricao(): string
    {
        return 'Das frequências lançadas nas aulas dos últimos 30 dias, a parcela que não é falta (a mesma contagem do risco de evasão).';
    }

    public function valor(ContextoPainel $contexto): ?float
    {
        $registros = FrequenciaEscolar::query()
            ->whereHas('cronogramaAula', fn ($q) => $q->whereDate('data', '>=', $contexto->hoje->subDays(30)->toDateString())->whereDate('data', '<=', $contexto->hoje->toDateString()))
            // Falta abonada não conta como falta (a só justificada continua contando): a mesma regra do risco de evasão.
            ->selectRaw("count(*) as total, sum(case when situacao = 'ausente' and abonada = 0 then 1 else 0 end) as ausentes")
            ->first();

        $total = (int) ($registros?->total ?? 0);

        if ($total === 0) {
            return null;
        }

        return round(($total - (int) $registros->ausentes) / $total * 100, 1);
    }
}

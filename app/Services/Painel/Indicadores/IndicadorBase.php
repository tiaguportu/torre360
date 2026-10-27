<?php

declare(strict_types=1);

namespace App\Services\Painel\Indicadores;

use App\Contracts\IndicadorDoPainel;
use App\Models\Matricula;
use App\Support\Painel\ContextoPainel;
use Illuminate\Database\Eloquent\Builder;

/**
 * Padrões comuns dos indicadores do painel.
 */
abstract class IndicadorBase implements IndicadorDoPainel
{
    public function suportaUnidade(): bool
    {
        return false;
    }

    public function url(): ?string
    {
        return null;
    }

    /**
     * Matrículas ativas hoje: situação ativa, ativada até hoje e ainda não desativada. A mesma regra de `Turma::matriculasAtivasCount()`,
     * aplicada a todas as turmas (ou às da unidade).
     *
     * @return Builder<Matricula>
     */
    protected function matriculasAtivas(ContextoPainel $contexto): Builder
    {
        return Matricula::query()
            ->without('turma')
            ->where('situacao', 'ativa')
            ->where(fn ($q) => $q->whereNull('data_ativacao')->orWhereDate('data_ativacao', '<=', $contexto->hoje->toDateString()))
            ->where(fn ($q) => $q->whereNull('data_desativacao')->orWhereDate('data_desativacao', '>', $contexto->hoje->toDateString()))
            ->when($contexto->daUnidade(), fn ($q, int $unidade) => $q->whereHas('turma.serie.curso', fn ($c) => $c->where('unidade_id', $unidade)));
    }
}

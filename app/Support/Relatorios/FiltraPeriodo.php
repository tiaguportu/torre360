<?php

declare(strict_types=1);

namespace App\Support\Relatorios;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Filtro "de/até" por data, comum às fontes de relatório (Onda 72). Datas inválidas são ignoradas em vez de derrubar o
 * relatório: o formulário já valida, e o relatório agendado precisa continuar rodando.
 */
trait FiltraPeriodo
{
    /**
     * @param  Builder<Model>  $consulta
     * @param  array<string, mixed>  $filtros
     */
    protected function aplicarPeriodo(Builder $consulta, string $coluna, array $filtros, string $chave): void
    {
        foreach (['de' => '>=', 'ate' => '<='] as $lado => $operador) {
            $data = DataDeFiltro::normalizar($filtros["{$chave}_{$lado}"] ?? null);

            if ($data !== null) {
                $consulta->whereDate($coluna, $operador, $data);
            }
        }
    }
}

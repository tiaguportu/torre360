<?php

declare(strict_types=1);

namespace App\Support\Pendencias;

use App\Models\PeriodoLetivo;

/**
 * O período letivo que as telas abrem por padrão: o mais recente que já começou, ou o mais recente de todos quando
 * nenhum começou. As filas da Mesa de pendências usam o mesmo critério, para contar o que a tela mostra.
 */
final class PeriodoEmCurso
{
    public static function atual(): ?PeriodoLetivo
    {
        return PeriodoLetivo::query()->whereDate('data_inicio', '<=', today())->orderByDesc('data_inicio')->first()
            ?? PeriodoLetivo::query()->orderByDesc('data_inicio')->first();
    }
}

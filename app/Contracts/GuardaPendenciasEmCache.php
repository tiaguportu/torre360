<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Uma fila da Mesa de pendências (Onda 71) cuja contagem é cara e fica guardada no cache, para todos os usuários. A Mesa
 * chama {@see esquecer()} no botão "Atualizar", para a contagem ser refeita na hora.
 */
interface GuardaPendenciasEmCache
{
    public function esquecer(): void;
}

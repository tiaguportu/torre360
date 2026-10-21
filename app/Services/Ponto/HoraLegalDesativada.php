<?php

declare(strict_types=1);

namespace App\Services\Ponto;

/**
 * Não consulta fonte alguma (PONTO_HORA_LEGAL=nenhuma). Os registros ficam marcados como "hora legal não verificada" e
 * o modo REP-P não pode ser ativado com esta implementação.
 */
class HoraLegalDesativada implements HoraLegalProvider
{
    public function desvioSegundos(): ?int
    {
        return null;
    }

    public function verificaHoraLegal(): bool
    {
        return false;
    }
}

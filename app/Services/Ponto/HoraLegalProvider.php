<?php

declare(strict_types=1);

namespace App\Services\Ponto;

/**
 * Fonte da Hora Legal Brasileira para conferir o relógio do servidor no momento da marcação.
 */
interface HoraLegalProvider
{
    /**
     * Diferença, em segundos, entre a hora legal e o relógio do servidor (positivo = servidor atrasado). `null` quando
     * não foi possível consultar. Nunca lança: a falha da consulta não pode impedir o trabalhador de registrar o ponto.
     */
    public function desvioSegundos(): ?int;

    /**
     * Esta implementação realmente consulta uma fonte oficial? (Usada pela conformidade do REP-P.)
     */
    public function verificaHoraLegal(): bool;
}

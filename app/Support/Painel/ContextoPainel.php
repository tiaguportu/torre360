<?php

declare(strict_types=1);

namespace App\Support\Painel;

use Carbon\CarbonImmutable;

/**
 * Em que data e para qual unidade o indicador é calculado. `unidadeId` 0 é a escola toda.
 */
final readonly class ContextoPainel
{
    public function __construct(
        public CarbonImmutable $hoje,
        public int $unidadeId = 0,
    ) {}

    public function daUnidade(): ?int
    {
        return $this->unidadeId > 0 ? $this->unidadeId : null;
    }
}

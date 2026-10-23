<?php

declare(strict_types=1);

namespace App\Support\Pendencias;

use Carbon\CarbonInterface;

/**
 * Uma linha da Mesa de pendências: quem ou o quê, o detalhe que ajuda a decidir por onde começar e, quando existir, desde
 * quando o item espera.
 */
final readonly class ItemPendencia
{
    public function __construct(
        public string $titulo,
        public string $detalhe = '',
        public ?CarbonInterface $desde = null,
        public ?string $url = null,
        public bool $atrasado = false,
    ) {}
}

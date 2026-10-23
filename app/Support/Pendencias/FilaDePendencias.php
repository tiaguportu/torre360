<?php

declare(strict_types=1);

namespace App\Support\Pendencias;

use App\Contracts\FontePendencia;

/**
 * O que a Mesa mostra de uma fila: a fonte, quantos itens esperam, quantos passaram do prazo e os primeiros da lista.
 * `erro` marca a fila que não conseguiu carregar (a Mesa segue com as outras).
 */
final readonly class FilaDePendencias
{
    /**
     * @param  list<ItemPendencia>  $itens
     */
    public function __construct(
        public FontePendencia $fonte,
        public int $total = 0,
        public int $atrasados = 0,
        public array $itens = [],
        public bool $erro = false,
    ) {}

    public function temItens(): bool
    {
        return $this->total > 0;
    }

    /**
     * Itens que a lista curta não mostra.
     */
    public function restantes(): int
    {
        return max(0, $this->total - count($this->itens));
    }
}

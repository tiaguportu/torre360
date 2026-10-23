<?php

declare(strict_types=1);

namespace App\Support\Contabil;

use Carbon\CarbonInterface;

/**
 * Uma partida dobrada da exportação contábil (Onda 73): um valor que sai de uma conta (crédito) e entra em outra (débito).
 * Conta nula quer dizer que o sistema não sabe qual conta usar; a apuração aponta isso como pendência e o arquivo só sai
 * quando não resta nenhuma.
 */
final readonly class LancamentoContabil
{
    public function __construct(
        public CarbonInterface $data,
        public ?string $contaDebito,
        public ?string $contaCredito,
        public float $valor,
        public string $historico,
        public string $documento,
        public ?string $centroCusto,
        public string $origem,
    ) {}
}

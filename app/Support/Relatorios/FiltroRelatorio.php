<?php

declare(strict_types=1);

namespace App\Support\Relatorios;

/**
 * Um filtro que a fonte de um relatório oferece (Onda 72): `data` (de/até, guardado como `{chave}_de` e `{chave}_ate`), `opcao`
 * (uma entre as opções) ou `numero`.
 */
final readonly class FiltroRelatorio
{
    /**
     * @param  array<string, string>  $opcoes  valor => rótulo, para o filtro do tipo `opcao`
     */
    public function __construct(
        public string $chave,
        public string $rotulo,
        public string $tipo,
        public array $opcoes = [],
    ) {}
}

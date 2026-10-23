<?php

declare(strict_types=1);

namespace App\Support\Relatorios;

use App\Enums\TipoColunaRelatorio;

/**
 * Uma coluna que a fonte de um relatório oferece (Onda 72). `sensivel` marca dado pessoal (nome, nascimento...): a tela avisa
 * quem monta o relatório e o arquivo só vai para quem tem a permissão da fonte.
 */
final readonly class ColunaRelatorio
{
    public function __construct(
        public string $chave,
        public string $rotulo,
        public TipoColunaRelatorio $tipo = TipoColunaRelatorio::Texto,
        public bool $sensivel = false,
        public bool $padrao = true,
    ) {}
}

<?php

namespace App\Enums;

/**
 * Como o valor de uma coluna de relatório é escrito no arquivo (Onda 72): moeda e decimal com vírgula, data em dia/mês/ano.
 */
enum TipoColunaRelatorio: string
{
    case Texto = 'texto';
    case Inteiro = 'inteiro';
    case Decimal = 'decimal';
    case Moeda = 'moeda';
    case Data = 'data';

    public function alinhaADireita(): bool
    {
        return in_array($this, [self::Inteiro, self::Decimal, self::Moeda], true);
    }
}

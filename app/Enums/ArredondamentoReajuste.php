<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ArredondamentoReajuste: string implements HasLabel
{
    case Centavos = 'centavos';
    case Real = 'real';
    case Dezena = 'dezena';

    public function getLabel(): string
    {
        return match ($this) {
            self::Centavos => 'Até o centavo',
            self::Real => 'Para o real inteiro',
            self::Dezena => 'Para múltiplo de R$ 10',
        };
    }

    /**
     * Aplica o arredondamento a um valor em reais (meio para cima).
     */
    public function aplicar(float $valor): float
    {
        return match ($this) {
            self::Centavos => round($valor, 2),
            self::Real => round($valor, 0),
            self::Dezena => round($valor / 10, 0) * 10,
        };
    }
}

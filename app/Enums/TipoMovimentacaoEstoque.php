<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TipoMovimentacaoEstoque: string implements HasColor, HasLabel
{
    case Entrada = 'entrada';
    case Saida = 'saida';
    case Ajuste = 'ajuste';

    public function getLabel(): string
    {
        return match ($this) {
            self::Entrada => 'Entrada',
            self::Saida => 'Saída',
            self::Ajuste => 'Ajuste de inventário',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Entrada => 'success',
            self::Saida => 'danger',
            self::Ajuste => 'warning',
        };
    }
}

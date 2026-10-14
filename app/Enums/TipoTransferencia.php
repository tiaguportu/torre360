<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum TipoTransferencia: string implements HasColor, HasIcon, HasLabel
{
    case Saida = 'saida';
    case Entrada = 'entrada';

    public function getLabel(): string
    {
        return match ($this) {
            self::Saida => 'Saída (para outra escola)',
            self::Entrada => 'Entrada (vindo de outra escola)',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Saida => 'danger',
            self::Entrada => 'success',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Saida => 'heroicon-o-arrow-up-right',
            self::Entrada => 'heroicon-o-arrow-down-left',
        };
    }
}

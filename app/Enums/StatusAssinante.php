<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusAssinante: string implements HasColor, HasLabel
{
    case Pendente = 'pendente';
    case Assinou = 'assinou';
    case Recusou = 'recusou';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Assinou => 'Assinou',
            self::Recusou => 'Recusou',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pendente => 'warning',
            self::Assinou => 'success',
            self::Recusou => 'danger',
        };
    }
}

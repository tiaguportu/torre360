<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusAutoavaliacaoDocente: string implements HasColor, HasLabel
{
    case Rascunho = 'rascunho';
    case Enviada = 'enviada';

    public function getLabel(): string
    {
        return match ($this) {
            self::Rascunho => 'Em preenchimento',
            self::Enviada => 'Enviada',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Rascunho => 'warning',
            self::Enviada => 'success',
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusListaMaterial: string implements HasColor, HasLabel
{
    case Rascunho = 'rascunho';
    case Publicada = 'publicada';

    public function getLabel(): string
    {
        return match ($this) {
            self::Rascunho => 'Rascunho',
            self::Publicada => 'Publicada para as famílias',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Rascunho => 'gray',
            self::Publicada => 'success',
        };
    }
}

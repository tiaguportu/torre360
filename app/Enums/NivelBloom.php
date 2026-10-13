<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum NivelBloom: string implements HasColor, HasLabel
{
    case Lembrar = 'Lembrar';
    case Compreender = 'Compreender';
    case Aplicar = 'Aplicar';
    case Analisar = 'Analisar';
    case Avaliar = 'Avaliar';
    case Criar = 'Criar';

    public function getLabel(): string
    {
        return $this->value;
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Lembrar => 'gray',
            self::Compreender => 'info',
            self::Aplicar => 'primary',
            self::Analisar => 'warning',
            self::Avaliar => 'danger',
            self::Criar => 'success',
        };
    }
}

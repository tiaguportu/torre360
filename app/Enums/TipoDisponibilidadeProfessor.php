<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TipoDisponibilidadeProfessor: string implements HasColor, HasLabel
{
    case Disponivel = 'disponivel';
    case Indisponivel = 'indisponivel';

    public function getLabel(): string
    {
        return match ($this) {
            self::Disponivel => 'Disponível',
            self::Indisponivel => 'Indisponível',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Disponivel => 'success',
            self::Indisponivel => 'danger',
        };
    }
}

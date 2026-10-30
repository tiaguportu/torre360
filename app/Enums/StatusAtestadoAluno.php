<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum StatusAtestadoAluno: string implements HasColor, HasIcon, HasLabel
{
    case Vigente = 'vigente';
    case Cancelado = 'cancelado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Vigente => 'Vigente',
            self::Cancelado => 'Cancelado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Vigente => 'success',
            self::Cancelado => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Vigente => 'heroicon-o-check-circle',
            self::Cancelado => 'heroicon-o-x-circle',
        };
    }
}

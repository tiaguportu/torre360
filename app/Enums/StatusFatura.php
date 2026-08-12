<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum StatusFatura: string implements HasColor, HasIcon, HasLabel
{
    case Pendente = 'pendente';
    case Pago = 'pago';
    case Atrasado = 'atrasado';
    case Cancelado = 'cancelado';
    case Parcial = 'parcial';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Pago => 'Pago',
            self::Atrasado => 'Atrasado',
            self::Cancelado => 'Cancelado',
            self::Parcial => 'Pago Parcialmente',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pendente => 'warning',
            self::Pago => 'success',
            self::Atrasado => 'danger',
            self::Cancelado => 'gray',
            self::Parcial => 'info',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Pendente => 'heroicon-m-clock',
            self::Pago => 'heroicon-m-check-circle',
            self::Atrasado => 'heroicon-m-exclamation-circle',
            self::Cancelado => 'heroicon-m-x-circle',
            self::Parcial => 'heroicon-m-minus-circle',
        };
    }
}

<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum StatusContaPagar: string implements HasColor, HasIcon, HasLabel
{
    case Pendente = 'pendente';
    case Pago = 'pago';
    case Atrasado = 'atrasado';
    case Cancelado = 'cancelado';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Pago => 'Pago',
            self::Atrasado => 'Atrasado',
            self::Cancelado => 'Cancelado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pendente => 'warning',
            self::Pago => 'success',
            self::Atrasado => 'danger',
            self::Cancelado => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Pendente => 'heroicon-m-clock',
            self::Pago => 'heroicon-m-check-circle',
            self::Atrasado => 'heroicon-m-exclamation-circle',
            self::Cancelado => 'heroicon-m-x-circle',
        };
    }
}

<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum StatusPeriodoFerias: string implements HasColor, HasIcon, HasLabel
{
    case Pendente = 'pendente';
    case Parcial = 'parcial';
    case Gozado = 'gozado';
    case Vencido = 'vencido';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Parcial => 'Gozado Parcialmente',
            self::Gozado => 'Gozado',
            self::Vencido => 'Vencido',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pendente => 'gray',
            self::Parcial => 'warning',
            self::Gozado => 'success',
            self::Vencido => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Pendente => 'heroicon-m-clock',
            self::Parcial => 'heroicon-m-sun',
            self::Gozado => 'heroicon-m-check-circle',
            self::Vencido => 'heroicon-m-exclamation-circle',
        };
    }
}

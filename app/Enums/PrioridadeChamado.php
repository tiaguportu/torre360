<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PrioridadeChamado: string implements HasColor, HasIcon, HasLabel
{
    case Baixa = 'baixa';
    case Normal = 'normal';
    case Alta = 'alta';
    case Urgente = 'urgente';

    public function getLabel(): string
    {
        return match ($this) {
            self::Baixa => 'Baixa',
            self::Normal => 'Normal',
            self::Alta => 'Alta',
            self::Urgente => 'Urgente',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Baixa => 'gray',
            self::Normal => 'info',
            self::Alta => 'warning',
            self::Urgente => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Baixa => 'heroicon-o-arrow-down',
            self::Normal => 'heroicon-o-minus',
            self::Alta => 'heroicon-o-arrow-up',
            self::Urgente => 'heroicon-o-exclamation-triangle',
        };
    }
}

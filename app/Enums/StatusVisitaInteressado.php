<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum StatusVisitaInteressado: string implements HasColor, HasIcon, HasLabel
{
    case Agendada = 'agendada';
    case Realizada = 'realizada';
    case Faltou = 'faltou';
    case Cancelada = 'cancelada';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Agendada => 'Agendada',
            self::Realizada => 'Realizada',
            self::Faltou => 'Não compareceu',
            self::Cancelada => 'Cancelada',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Agendada => 'info',
            self::Realizada => 'success',
            self::Faltou => 'danger',
            self::Cancelada => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Agendada => 'heroicon-m-calendar-days',
            self::Realizada => 'heroicon-m-check-badge',
            self::Faltou => 'heroicon-m-user-minus',
            self::Cancelada => 'heroicon-m-x-circle',
        };
    }
}

<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum StatusRsvp: string implements HasColor, HasIcon, HasLabel
{
    case Pendente = 'pendente';
    case Confirmado = 'confirmado';
    case Recusado = 'recusado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Confirmado => 'Presença Confirmada',
            self::Recusado => 'Não Comparecerá',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pendente => 'warning',
            self::Confirmado => 'success',
            self::Recusado => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Pendente => 'heroicon-o-clock',
            self::Confirmado => 'heroicon-o-check-circle',
            self::Recusado => 'heroicon-o-x-circle',
        };
    }
}

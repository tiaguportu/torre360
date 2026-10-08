<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum StatusConsentimento: string implements HasColor, HasIcon, HasLabel
{
    case Pendente = 'pendente';
    case Autorizado = 'autorizado';
    case NaoAutorizado = 'nao_autorizado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Autorizado => 'Autorizado',
            self::NaoAutorizado => 'Não Autorizado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pendente => 'warning',
            self::Autorizado => 'success',
            self::NaoAutorizado => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Pendente => 'heroicon-o-clock',
            self::Autorizado => 'heroicon-o-check-circle',
            self::NaoAutorizado => 'heroicon-o-x-circle',
        };
    }
}

<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum StatusBolsa: string implements HasColor, HasIcon, HasLabel
{
    case Solicitada = 'solicitada';
    case Aprovada = 'aprovada';
    case Recusada = 'recusada';
    case Encerrada = 'encerrada';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Solicitada => 'Solicitada',
            self::Aprovada => 'Aprovada',
            self::Recusada => 'Recusada',
            self::Encerrada => 'Encerrada',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Solicitada => 'warning',
            self::Aprovada => 'success',
            self::Recusada => 'danger',
            self::Encerrada => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Solicitada => 'heroicon-m-clock',
            self::Aprovada => 'heroicon-m-check-circle',
            self::Recusada => 'heroicon-m-x-circle',
            self::Encerrada => 'heroicon-m-archive-box',
        };
    }
}

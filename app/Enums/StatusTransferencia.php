<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum StatusTransferencia: string implements HasColor, HasIcon, HasLabel
{
    case EmAndamento = 'em_andamento';
    case Concluida = 'concluida';
    case Cancelada = 'cancelada';

    public function getLabel(): string
    {
        return match ($this) {
            self::EmAndamento => 'Em Andamento',
            self::Concluida => 'Concluída',
            self::Cancelada => 'Cancelada',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::EmAndamento => 'warning',
            self::Concluida => 'success',
            self::Cancelada => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::EmAndamento => 'heroicon-o-clock',
            self::Concluida => 'heroicon-o-check-circle',
            self::Cancelada => 'heroicon-o-x-circle',
        };
    }
}

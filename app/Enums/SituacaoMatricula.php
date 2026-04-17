<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum SituacaoMatricula: string implements HasColor, HasIcon, HasLabel
{
    case ATIVA = 'ativa';
    case TRANCADA = 'trancada';
    case CANCELADA = 'cancelada';
    case CONCLUIDA = 'concluido';
    case RESERVA = 'reserva';
    case PENDENTE = 'pendente';
    case EVASAO = 'evasao';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::ATIVA => 'Ativa',
            self::TRANCADA => 'Trancada',
            self::CANCELADA => 'Cancelada',
            self::CONCLUIDA => 'Concluída',
            self::RESERVA => 'Reserva',
            self::PENDENTE => 'Pendente',
            self::EVASAO => 'Evasão',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::ATIVA => 'success',
            self::TRANCADA => 'warning',
            self::CANCELADA => 'danger',
            self::CONCLUIDA => 'info',
            self::RESERVA => 'gray',
            self::PENDENTE => 'warning',
            self::EVASAO => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::ATIVA => 'heroicon-m-check-circle',
            self::TRANCADA => 'heroicon-m-pause-circle',
            self::CANCELADA => 'heroicon-m-x-circle',
            self::CONCLUIDA => 'heroicon-m-academic-cap',
            self::RESERVA => 'heroicon-m-bookmark',
            self::PENDENTE => 'heroicon-m-clock',
            self::EVASAO => 'heroicon-m-arrow-right-start-on-rectangle',
        };
    }
}

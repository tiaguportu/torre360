<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Situação da reunião (Ondas 27 e 32): agendada → realizada → ata fechada (imutável), ou cancelada.
 */
enum StatusReuniao: string implements HasColor, HasLabel
{
    case Agendada = 'agendada';
    case Realizada = 'realizada';
    case AtaFechada = 'ata_fechada';
    case Cancelada = 'cancelada';

    public function getLabel(): string
    {
        return match ($this) {
            self::Agendada => 'Agendada',
            self::Realizada => 'Realizada, ata em aberto',
            self::AtaFechada => 'Ata fechada',
            self::Cancelada => 'Cancelada',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Agendada => 'info',
            self::Realizada => 'warning',
            self::AtaFechada => 'success',
            self::Cancelada => 'gray',
        };
    }
}

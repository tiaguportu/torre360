<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum StatusSacolaLeitura: string implements HasColor, HasIcon, HasLabel
{
    case EmCirculacao = 'em_circulacao';
    case ParcialmenteDevolvida = 'parcialmente_devolvida';
    case Devolvida = 'devolvida';
    case Atrasada = 'atrasada';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::EmCirculacao => 'Em Circulação',
            self::ParcialmenteDevolvida => 'Parcialmente Devolvida',
            self::Devolvida => 'Devolvida',
            self::Atrasada => 'Atrasada',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::EmCirculacao => 'info',
            self::ParcialmenteDevolvida => 'warning',
            self::Devolvida => 'success',
            self::Atrasada => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::EmCirculacao => 'heroicon-m-shopping-bag',
            self::ParcialmenteDevolvida => 'heroicon-m-clock',
            self::Devolvida => 'heroicon-m-check-circle',
            self::Atrasada => 'heroicon-m-exclamation-circle',
        };
    }
}

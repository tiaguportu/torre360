<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum SituacaoCargaLetiva: string implements HasColor, HasIcon, HasLabel
{
    case EmDia = 'em_dia';
    case EmRisco = 'em_risco';
    case Insuficiente = 'insuficiente';
    case SemMinimo = 'sem_minimo';

    public function getLabel(): string
    {
        return match ($this) {
            self::EmDia => 'Em dia',
            self::EmRisco => 'Em risco',
            self::Insuficiente => 'Calendário insuficiente',
            self::SemMinimo => 'Sem mínimo definido',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::EmDia => 'success',
            self::EmRisco => 'warning',
            self::Insuficiente => 'danger',
            self::SemMinimo => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::EmDia => 'heroicon-o-check-circle',
            self::EmRisco => 'heroicon-o-exclamation-triangle',
            self::Insuficiente => 'heroicon-o-x-circle',
            self::SemMinimo => 'heroicon-o-minus-circle',
        };
    }
}

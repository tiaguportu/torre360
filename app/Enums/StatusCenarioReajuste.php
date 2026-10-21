<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusCenarioReajuste: string implements HasColor, HasLabel
{
    case Simulacao = 'simulacao';
    case Aprovado = 'aprovado';
    case Aplicado = 'aplicado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Simulacao => 'Simulação',
            self::Aprovado => 'Aprovado',
            self::Aplicado => 'Aplicado na campanha',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Simulacao => 'gray',
            self::Aprovado => 'info',
            self::Aplicado => 'success',
        };
    }
}

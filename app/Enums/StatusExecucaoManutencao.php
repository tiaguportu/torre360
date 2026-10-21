<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusExecucaoManutencao: string implements HasColor, HasLabel
{
    case Prevista = 'prevista';
    case Concluida = 'concluida';
    case Cancelada = 'cancelada';

    public function getLabel(): string
    {
        return match ($this) {
            self::Prevista => 'Prevista',
            self::Concluida => 'Concluída',
            self::Cancelada => 'Cancelada',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Prevista => 'warning',
            self::Concluida => 'success',
            self::Cancelada => 'gray',
        };
    }
}

<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum OrigemExecucaoRelatorio: string implements HasLabel
{
    case Manual = 'manual';
    case Agendada = 'agendada';

    public function getLabel(): string
    {
        return match ($this) {
            self::Manual => 'Pedido na tela',
            self::Agendada => 'Agendamento',
        };
    }
}

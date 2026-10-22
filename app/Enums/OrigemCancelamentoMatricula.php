<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum OrigemCancelamentoMatricula: string implements HasLabel
{
    case Familia = 'familia';
    case Escola = 'escola';

    public function getLabel(): string
    {
        return match ($this) {
            self::Familia => 'Pedido da família',
            self::Escola => 'Registrado pela escola',
        };
    }
}

<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RegimeContratacao: string implements HasLabel
{
    case CLT = 'clt';
    case Estatutario = 'estatutario';
    case PJ = 'pj';
    case Estagio = 'estagio';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::CLT => 'CLT',
            self::Estatutario => 'Estatutário',
            self::PJ => 'PJ',
            self::Estagio => 'Estágio',
        };
    }
}

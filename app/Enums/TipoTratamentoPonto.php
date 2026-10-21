<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TipoTratamentoPonto: string implements HasLabel
{
    case Inclusao = 'inclusao';
    case Desconsiderar = 'desconsiderar';
    case Abono = 'abono';

    public function getLabel(): string
    {
        return match ($this) {
            self::Inclusao => 'Incluir marcação esquecida',
            self::Desconsiderar => 'Desconsiderar marcação indevida',
            self::Abono => 'Abonar tempo (atestado, folga, falta justificada)',
        };
    }
}

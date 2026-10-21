<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ModoPonto: string implements HasColor, HasLabel
{
    case ControleInterno = 'controle_interno';
    case RepA = 'rep_a';
    case RepP = 'rep_p';

    public function getLabel(): string
    {
        return match ($this) {
            self::ControleInterno => 'Controle interno (sem valor de REP)',
            self::RepA => 'REP-A (alternativo, por acordo coletivo)',
            self::RepP => 'REP-P (registrador via programa)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::ControleInterno => 'gray',
            self::RepA, self::RepP => 'success',
        };
    }

    /** O modo declara conformidade com a Portaria MTP 671/2021 e por isso exige requisitos. */
    public function ehOficial(): bool
    {
        return $this !== self::ControleInterno;
    }
}

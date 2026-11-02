<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Como terminou um requerimento da família (Onda 80): atendido ou indeferido, este com justificativa obrigatória.
 */
enum ResultadoChamado: string implements HasColor, HasLabel
{
    case Atendido = 'atendido';
    case Indeferido = 'indeferido';

    public function getLabel(): string
    {
        return match ($this) {
            self::Atendido => 'Atendido',
            self::Indeferido => 'Indeferido',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Atendido => 'success',
            self::Indeferido => 'danger',
        };
    }
}

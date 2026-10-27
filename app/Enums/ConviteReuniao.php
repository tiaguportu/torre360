<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Resposta de quem foi convidado para a reunião (Onda 32).
 */
enum ConviteReuniao: string implements HasColor, HasLabel
{
    case Pendente = 'pendente';
    case Aceito = 'aceito';
    case Recusado = 'recusado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pendente => 'Sem resposta',
            self::Aceito => 'Vai participar',
            self::Recusado => 'Não pode ir',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pendente => 'gray',
            self::Aceito => 'success',
            self::Recusado => 'danger',
        };
    }
}

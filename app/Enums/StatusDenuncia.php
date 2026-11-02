<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusDenuncia: string implements HasColor, HasLabel
{
    case Recebida = 'recebida';
    case EmApuracao = 'em_apuracao';
    case Concluida = 'concluida';
    case Arquivada = 'arquivada';

    public function getLabel(): string
    {
        return match ($this) {
            self::Recebida => 'Recebida',
            self::EmApuracao => 'Em apuração',
            self::Concluida => 'Concluída',
            self::Arquivada => 'Arquivada',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Recebida => 'info',
            self::EmApuracao => 'warning',
            self::Concluida => 'success',
            self::Arquivada => 'gray',
        };
    }

    public function estaEncerrada(): bool
    {
        return in_array($this, [self::Concluida, self::Arquivada], true);
    }
}

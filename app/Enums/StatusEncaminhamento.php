<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Situação de um encaminhamento de reunião (Ondas 27 e 32). "Vencido" não é situação: é aberto com o prazo passado.
 */
enum StatusEncaminhamento: string implements HasColor, HasLabel
{
    case Aberto = 'aberto';
    case Feito = 'feito';
    case Cancelado = 'cancelado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Aberto => 'Aberto',
            self::Feito => 'Feito',
            self::Cancelado => 'Cancelado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Aberto => 'warning',
            self::Feito => 'success',
            self::Cancelado => 'gray',
        };
    }
}

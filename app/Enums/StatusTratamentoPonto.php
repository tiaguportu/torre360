<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusTratamentoPonto: string implements HasColor, HasLabel
{
    case Pendente = 'pendente';
    case Aprovado = 'aprovado';
    case Recusado = 'recusado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pendente => 'Aguardando aprovação',
            self::Aprovado => 'Aprovado',
            self::Recusado => 'Recusado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pendente => 'warning',
            self::Aprovado => 'success',
            self::Recusado => 'danger',
        };
    }
}

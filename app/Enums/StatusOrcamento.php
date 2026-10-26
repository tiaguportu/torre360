<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusOrcamento: string implements HasColor, HasLabel
{
    case Rascunho = 'rascunho';
    case Aprovado = 'aprovado';
    case Substituido = 'substituido';

    public function getLabel(): string
    {
        return match ($this) {
            self::Rascunho => 'Rascunho',
            self::Aprovado => 'Aprovado',
            self::Substituido => 'Substituído por revisão',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Rascunho => 'warning',
            self::Aprovado => 'success',
            self::Substituido => 'gray',
        };
    }
}

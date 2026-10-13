<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusAdaptacaoPei: string implements HasColor, HasLabel
{
    case Rascunho = 'rascunho';
    case Revisado = 'revisado';
    case Aprovado = 'aprovado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Rascunho => 'Rascunho (IA)',
            self::Revisado => 'Revisado',
            self::Aprovado => 'Aprovado (Oficial)',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Rascunho => 'warning',
            self::Revisado => 'info',
            self::Aprovado => 'success',
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum StatusParecerDescritivo: string implements HasColor, HasIcon, HasLabel
{
    case Rascunho = 'rascunho';
    case EmRevisao = 'em_revisao';
    case Publicado = 'publicado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Rascunho => 'Rascunho',
            self::EmRevisao => 'Em revisão',
            self::Publicado => 'Publicado para a família',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Rascunho => 'gray',
            self::EmRevisao => 'warning',
            self::Publicado => 'success',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Rascunho => 'heroicon-o-pencil',
            self::EmRevisao => 'heroicon-o-eye',
            self::Publicado => 'heroicon-o-check-badge',
        };
    }
}

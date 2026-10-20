<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum StatusAvaliacaoDocente: string implements HasColor, HasIcon, HasLabel
{
    case Rascunho = 'rascunho';
    case Finalizada = 'finalizada';

    public function getLabel(): string
    {
        return match ($this) {
            self::Rascunho => 'Rascunho',
            self::Finalizada => 'Finalizada',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Rascunho => 'warning',
            self::Finalizada => 'success',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Rascunho => 'heroicon-o-pencil',
            self::Finalizada => 'heroicon-o-lock-closed',
        };
    }
}

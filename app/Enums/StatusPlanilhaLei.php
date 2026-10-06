<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum StatusPlanilhaLei: string implements HasColor, HasIcon, HasLabel
{
    case Rascunho = 'rascunho';
    case EmAnalise = 'em_analise';
    case Homologada = 'homologada';
    case Publicada = 'publicada';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Rascunho => 'Rascunho',
            self::EmAnalise => 'Em Análise',
            self::Homologada => 'Homologada pela Diretoria',
            self::Publicada => 'Publicada / Afixada (Oficial)',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Rascunho => 'gray',
            self::EmAnalise => 'warning',
            self::Homologada => 'info',
            self::Publicada => 'success',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Rascunho => 'heroicon-o-pencil-square',
            self::EmAnalise => 'heroicon-o-clock',
            self::Homologada => 'heroicon-o-check-badge',
            self::Publicada => 'heroicon-o-megaphone',
        };
    }
}

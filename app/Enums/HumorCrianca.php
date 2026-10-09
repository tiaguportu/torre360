<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum HumorCrianca: string implements HasColor, HasIcon, HasLabel
{
    case Feliz = 'feliz';
    case Tranquilo = 'tranquilo';
    case Agitado = 'agitado';
    case Irritado = 'irritado';
    case Sonolento = 'sonolento';
    case Choroso = 'choroso';

    public function getLabel(): string
    {
        return match ($this) {
            self::Feliz => 'Feliz',
            self::Tranquilo => 'Tranquilo(a)',
            self::Agitado => 'Agitado(a)',
            self::Irritado => 'Irritado(a)',
            self::Sonolento => 'Sonolento(a)',
            self::Choroso => 'Choroso(a)',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Feliz => 'success',
            self::Tranquilo => 'info',
            self::Agitado => 'warning',
            self::Irritado => 'danger',
            self::Sonolento => 'gray',
            self::Choroso => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Feliz => 'heroicon-o-face-smile',
            self::Tranquilo => 'heroicon-o-face-smile',
            self::Agitado => 'heroicon-o-bolt',
            self::Irritado => 'heroicon-o-face-frown',
            self::Sonolento => 'heroicon-o-moon',
            self::Choroso => 'heroicon-o-face-frown',
        };
    }
}

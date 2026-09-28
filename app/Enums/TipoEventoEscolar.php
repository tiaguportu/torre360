<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum TipoEventoEscolar: string implements HasColor, HasIcon, HasLabel
{
    case ReuniaoPais = 'reuniao_pais';
    case PasseioCultural = 'passeio_cultural';
    case FestaComemorativa = 'festa_comemorativa';
    case Palestra = 'palestra';
    case Formatura = 'formatura';
    case Outro = 'outro';

    public function getLabel(): string
    {
        return match ($this) {
            self::ReuniaoPais => 'Reunião de Pais e Mestres',
            self::PasseioCultural => 'Passeio Cultural / Pedagógico',
            self::FestaComemorativa => 'Festa / Celebração Escolar',
            self::Palestra => 'Palestra / Workshop',
            self::Formatura => 'Formatura / Solenidade',
            self::Outro => 'Outro Evento',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::ReuniaoPais => 'info',
            self::PasseioCultural => 'warning',
            self::FestaComemorativa => 'success',
            self::Palestra => 'primary',
            self::Formatura => 'purple',
            self::Outro => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::ReuniaoPais => 'heroicon-o-user-group',
            self::PasseioCultural => 'heroicon-o-academic-cap',
            self::FestaComemorativa => 'heroicon-o-sparkles',
            self::Palestra => 'heroicon-o-microphone',
            self::Formatura => 'heroicon-o-trophy',
            self::Outro => 'heroicon-o-calendar',
        };
    }
}

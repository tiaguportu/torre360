<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum NivelAlcadaComercial: string implements HasColor, HasIcon, HasLabel
{
    case Consultor = 'consultor';
    case Coordenacao = 'coordenacao';
    case Diretoria = 'diretoria';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Consultor => 'Alçada Consultor (Até 7%)',
            self::Coordenacao => 'Alçada Coordenação (Até 15%)',
            self::Diretoria => 'Alçada Diretoria Geral (> 15%)',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Consultor => 'success',
            self::Coordenacao => 'warning',
            self::Diretoria => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Consultor => 'heroicon-m-user',
            self::Coordenacao => 'heroicon-m-user-group',
            self::Diretoria => 'heroicon-m-building-office-2',
        };
    }
}

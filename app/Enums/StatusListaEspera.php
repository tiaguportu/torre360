<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum StatusListaEspera: string implements HasColor, HasIcon, HasLabel
{
    case Aguardando = 'aguardando';
    case Notificado = 'notificado';
    case Convertido = 'convertido';
    case Desistiu = 'desistiu';

    public function getLabel(): string
    {
        return match ($this) {
            self::Aguardando => 'Aguardando Vaga',
            self::Notificado => 'Notificado',
            self::Convertido => 'Convertido em Matrícula',
            self::Desistiu => 'Desistiu',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Aguardando => 'gray',
            self::Notificado => 'info',
            self::Convertido => 'success',
            self::Desistiu => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Aguardando => 'heroicon-o-clock',
            self::Notificado => 'heroicon-o-bell-alert',
            self::Convertido => 'heroicon-o-check-circle',
            self::Desistiu => 'heroicon-o-x-circle',
        };
    }
}

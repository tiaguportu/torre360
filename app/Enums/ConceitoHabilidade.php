<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ConceitoHabilidade: string implements HasColor, HasIcon, HasLabel
{
    case REALIZA_BEM = 'realiza_bem';
    case EM_DESENVOLVIMENTO = 'em_desenvolvimento';
    case NAO_REALIZA = 'nao_realiza';
    case NAO_OBSERVADO = 'nao_observado';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::REALIZA_BEM => 'Realiza bem',
            self::EM_DESENVOLVIMENTO => 'Em desenvolvimento',
            self::NAO_REALIZA => 'Não realiza',
            self::NAO_OBSERVADO => 'Não observado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::REALIZA_BEM => 'success',
            self::EM_DESENVOLVIMENTO => 'warning',
            self::NAO_REALIZA => 'danger',
            self::NAO_OBSERVADO => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::REALIZA_BEM => 'heroicon-m-check-badge',
            self::EM_DESENVOLVIMENTO => 'heroicon-m-arrow-path',
            self::NAO_REALIZA => 'heroicon-m-x-circle',
            self::NAO_OBSERVADO => 'heroicon-m-eye-slash',
        };
    }
}

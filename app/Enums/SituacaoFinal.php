<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum SituacaoFinal: string implements HasColor, HasIcon, HasLabel
{
    case APROVADO = 'aprovado';
    case RECUPERACAO = 'recuperacao';
    case REPROVADO = 'reprovado';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::APROVADO => 'Aprovado',
            self::RECUPERACAO => 'Recuperação',
            self::REPROVADO => 'Reprovado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::APROVADO => 'success',
            self::RECUPERACAO => 'warning',
            self::REPROVADO => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::APROVADO => 'heroicon-m-check-circle',
            self::RECUPERACAO => 'heroicon-m-exclamation-triangle',
            self::REPROVADO => 'heroicon-m-x-circle',
        };
    }
}

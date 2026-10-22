<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum TipoProcessoFuncionario: string implements HasColor, HasIcon, HasLabel
{
    case Admissao = 'admissao';
    case Desligamento = 'desligamento';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admissao => 'Admissão',
            self::Desligamento => 'Desligamento',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Admissao => 'success',
            self::Desligamento => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Admissao => 'heroicon-o-user-plus',
            self::Desligamento => 'heroicon-o-user-minus',
        };
    }
}

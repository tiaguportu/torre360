<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum StatusProcessoFuncionario: string implements HasColor, HasIcon, HasLabel
{
    case EmAndamento = 'em_andamento';
    case Concluido = 'concluido';
    case Cancelado = 'cancelado';

    public function getLabel(): string
    {
        return match ($this) {
            self::EmAndamento => 'Em andamento',
            self::Concluido => 'Concluído',
            self::Cancelado => 'Cancelado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::EmAndamento => 'warning',
            self::Concluido => 'success',
            self::Cancelado => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::EmAndamento => 'heroicon-o-clock',
            self::Concluido => 'heroicon-o-check-circle',
            self::Cancelado => 'heroicon-o-x-circle',
        };
    }
}

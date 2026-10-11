<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum StatusRetencaoTarefa: string implements HasColor, HasIcon, HasLabel
{
    case Pendente = 'pendente';
    case EmAndamento = 'em_andamento';
    case Concluido = 'concluido';
    case Cancelado = 'cancelado';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::EmAndamento => 'Em Andamento',
            self::Concluido => 'Concluído',
            self::Cancelado => 'Cancelado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pendente => 'danger',
            self::EmAndamento => 'warning',
            self::Concluido => 'success',
            self::Cancelado => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Pendente => 'heroicon-m-clock',
            self::EmAndamento => 'heroicon-m-arrow-path',
            self::Concluido => 'heroicon-m-check-circle',
            self::Cancelado => 'heroicon-m-x-circle',
        };
    }
}

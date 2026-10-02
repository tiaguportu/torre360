<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum StatusBemPatrimonial: string implements HasColor, HasIcon, HasLabel
{
    case EmUso = 'em_uso';
    case EmManutencao = 'em_manutencao';
    case Baixado = 'baixado';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::EmUso => 'Em Uso',
            self::EmManutencao => 'Em Manutenção',
            self::Baixado => 'Baixado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::EmUso => 'success',
            self::EmManutencao => 'warning',
            self::Baixado => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::EmUso => 'heroicon-m-check-circle',
            self::EmManutencao => 'heroicon-m-wrench-screwdriver',
            self::Baixado => 'heroicon-m-archive-box-x-mark',
        };
    }
}

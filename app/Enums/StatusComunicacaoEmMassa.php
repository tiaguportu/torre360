<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum StatusComunicacaoEmMassa: string implements HasColor, HasIcon, HasLabel
{
    case Rascunho = 'rascunho';
    case Enviando = 'enviando';
    case Concluida = 'concluida';
    case Falhou = 'falhou';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Rascunho => 'Rascunho',
            self::Enviando => 'Enviando',
            self::Concluida => 'Concluída',
            self::Falhou => 'Falhou',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Rascunho => 'gray',
            self::Enviando => 'warning',
            self::Concluida => 'success',
            self::Falhou => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Rascunho => 'heroicon-m-pencil',
            self::Enviando => 'heroicon-m-paper-airplane',
            self::Concluida => 'heroicon-m-check-badge',
            self::Falhou => 'heroicon-m-x-circle',
        };
    }
}

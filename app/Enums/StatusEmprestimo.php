<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum StatusEmprestimo: string implements HasColor, HasIcon, HasLabel
{
    case Emprestado = 'emprestado';
    case Devolvido = 'devolvido';
    case Atrasado = 'atrasado';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Emprestado => 'Emprestado',
            self::Devolvido => 'Devolvido',
            self::Atrasado => 'Atrasado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Emprestado => 'warning',
            self::Devolvido => 'success',
            self::Atrasado => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Emprestado => 'heroicon-m-book-open',
            self::Devolvido => 'heroicon-m-check-circle',
            self::Atrasado => 'heroicon-m-exclamation-circle',
        };
    }
}

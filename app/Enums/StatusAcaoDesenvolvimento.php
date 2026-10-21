<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusAcaoDesenvolvimento: string implements HasColor, HasLabel
{
    case Pendente = 'pendente';
    case EmAndamento = 'em_andamento';
    case Concluida = 'concluida';
    case Cancelada = 'cancelada';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::EmAndamento => 'Em andamento',
            self::Concluida => 'Concluída',
            self::Cancelada => 'Cancelada',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pendente => 'gray',
            self::EmAndamento => 'info',
            self::Concluida => 'success',
            self::Cancelada => 'danger',
        };
    }

    /** A ação ainda precisa de trabalho (conta para o atraso e impede concluir o plano). */
    public function emAberto(): bool
    {
        return in_array($this, [self::Pendente, self::EmAndamento], true);
    }
}

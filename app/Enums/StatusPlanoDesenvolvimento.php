<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusPlanoDesenvolvimento: string implements HasColor, HasLabel
{
    case Rascunho = 'rascunho';
    case Ativo = 'ativo';
    case Concluido = 'concluido';

    public function getLabel(): string
    {
        return match ($this) {
            self::Rascunho => 'Rascunho da coordenação',
            self::Ativo => 'Em andamento',
            self::Concluido => 'Concluído',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Rascunho => 'gray',
            self::Ativo => 'info',
            self::Concluido => 'success',
        };
    }
}

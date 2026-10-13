<?php

declare(strict_types=1);

namespace App\Enums;

enum EstadoLeituraQuestao: string
{
    case Valida = 'valida';
    case EmBranco = 'em_branco';
    case Dupla = 'dupla';
    case Ambigua = 'ambigua';
    case Ilegivel = 'ilegivel';

    public function label(): string
    {
        return match ($this) {
            self::Valida => 'Válida',
            self::EmBranco => 'Em Branco',
            self::Dupla => 'Dupla Marcação',
            self::Ambigua => 'Marcação Ambígua',
            self::Ilegivel => 'Ilegível',
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Enums;

enum ModoAnulacaoQuestao: string
{
    case PontuaTodos = 'pontua_todos';
    case Desconsidera = 'desconsidera';

    public function label(): string
    {
        return match ($this) {
            self::PontuaTodos => 'Pontuar para todos os alunos',
            self::Desconsidera => 'Desconsiderar no peso total da prova',
        };
    }
}

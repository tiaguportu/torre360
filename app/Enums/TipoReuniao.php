<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Tipos de reunião interna (Ondas 27 e 32). O conselho de classe é uma reunião com deliberação por aluno; a devolutiva individual
 * é sigilosa por natureza.
 */
enum TipoReuniao: string implements HasColor, HasLabel
{
    case ConselhoClasse = 'conselho_classe';
    case Pedagogica = 'pedagogica';
    case Devolutiva = 'devolutiva';
    case Formacao = 'formacao';
    case Outra = 'outra';

    public function getLabel(): string
    {
        return match ($this) {
            self::ConselhoClasse => 'Conselho de classe',
            self::Pedagogica => 'Reunião pedagógica',
            self::Devolutiva => 'Devolutiva individual',
            self::Formacao => 'Formação',
            self::Outra => 'Outra reunião',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::ConselhoClasse => 'primary',
            self::Pedagogica => 'info',
            self::Devolutiva => 'warning',
            self::Formacao => 'success',
            self::Outra => 'gray',
        };
    }

    /**
     * Reunião individual sobre uma pessoa: só quem organiza e a pessoa convidada a veem.
     */
    public function sempreConfidencial(): bool
    {
        return $this === self::Devolutiva;
    }

    public function temAlunos(): bool
    {
        return $this === self::ConselhoClasse;
    }
}

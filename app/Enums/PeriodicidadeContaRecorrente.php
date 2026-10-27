<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Com que frequência uma despesa fixa se repete (Onda 78).
 */
enum PeriodicidadeContaRecorrente: string implements HasLabel
{
    case Mensal = 'mensal';
    case Bimestral = 'bimestral';
    case Trimestral = 'trimestral';
    case Semestral = 'semestral';
    case Anual = 'anual';

    public function getLabel(): string
    {
        return match ($this) {
            self::Mensal => 'Mensal',
            self::Bimestral => 'Bimestral',
            self::Trimestral => 'Trimestral',
            self::Semestral => 'Semestral',
            self::Anual => 'Anual',
        };
    }

    /**
     * Intervalo, em meses, entre um vencimento e o seguinte.
     */
    public function meses(): int
    {
        return match ($this) {
            self::Mensal => 1,
            self::Bimestral => 2,
            self::Trimestral => 3,
            self::Semestral => 6,
            self::Anual => 12,
        };
    }
}

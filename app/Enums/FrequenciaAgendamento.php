<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Com que frequência um relatório agendado roda (Onda 72). O dia é o da semana (1 = segunda a 7 = domingo) na frequência
 * semanal e o do mês (1 a 28, para valer em todos os meses) na mensal.
 */
enum FrequenciaAgendamento: string implements HasLabel
{
    case Diaria = 'diaria';
    case Semanal = 'semanal';
    case Mensal = 'mensal';

    public function getLabel(): string
    {
        return match ($this) {
            self::Diaria => 'Todo dia',
            self::Semanal => 'Toda semana',
            self::Mensal => 'Todo mês',
        };
    }

    public function usaDia(): bool
    {
        return $this !== self::Diaria;
    }
}

<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Formas de compensação de jornada previstas na CLT, art. 59: o prazo máximo muda conforme o instrumento.
 */
enum TipoAcordoBancoHoras: string implements HasLabel
{
    /** Acordo individual escrito: compensação em até 6 meses (§ 5º). */
    case IndividualEscrito = 'individual_escrito';
    /** Acordo ou convenção coletiva: compensação em até 12 meses (§ 2º). */
    case Coletivo = 'coletivo';
    /** Acordo individual (inclusive tácito) com compensação dentro do mesmo mês (§ 6º). */
    case MesmoMes = 'mesmo_mes';

    public function getLabel(): string
    {
        return match ($this) {
            self::IndividualEscrito => 'Acordo individual escrito (até 6 meses)',
            self::Coletivo => 'Acordo ou convenção coletiva (até 12 meses)',
            self::MesmoMes => 'Compensação no mesmo mês',
        };
    }

    /** Prazo máximo, em meses, para compensar as horas. `0` significa até o fim do mês do crédito. */
    public function prazoMaximoMeses(): int
    {
        return match ($this) {
            self::IndividualEscrito => 6,
            self::Coletivo => 12,
            self::MesmoMes => 0,
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Situação de um plano de manutenção em relação ao vencimento (calculada, não gravada).
 */
enum SituacaoPlanoManutencao: string implements HasColor, HasLabel
{
    case Vencido = 'vencido';
    case AVencer = 'a_vencer';
    case EmDia = 'em_dia';
    case Inativo = 'inativo';

    public function getLabel(): string
    {
        return match ($this) {
            self::Vencido => 'Vencido',
            self::AVencer => 'A vencer',
            self::EmDia => 'Em dia',
            self::Inativo => 'Inativo',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Vencido => 'danger',
            self::AVencer => 'warning',
            self::EmDia => 'success',
            self::Inativo => 'gray',
        };
    }
}

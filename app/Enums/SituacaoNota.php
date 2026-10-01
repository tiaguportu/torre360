<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Justificativa para uma nota sem valor: resolve a pendência de lançamento
 * sem entrar no cálculo de médias.
 */
enum SituacaoNota: string implements HasColor, HasLabel
{
    case FALTOU = 'faltou';
    case NAO_SE_APLICA = 'nao_se_aplica';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::FALTOU => 'Faltou',
            self::NAO_SE_APLICA => 'Não se aplica',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::FALTOU => 'warning',
            self::NAO_SE_APLICA => 'gray',
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum OrigemLancamentoBancoHoras: string implements HasLabel
{
    case ApuracaoDia = 'apuracao_dia';
    case Compensacao = 'compensacao';
    case Expiracao = 'expiracao';
    case AjusteManual = 'ajuste_manual';

    public function getLabel(): string
    {
        return match ($this) {
            self::ApuracaoDia => 'Apuração do dia',
            self::Compensacao => 'Compensação (folga)',
            self::Expiracao => 'Prazo vencido (a pagar como hora extra)',
            self::AjusteManual => 'Ajuste manual',
        };
    }
}

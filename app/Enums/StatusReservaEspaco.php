<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum StatusReservaEspaco: string implements HasColor, HasIcon, HasLabel
{
    case Solicitada = 'solicitada';
    case Confirmada = 'confirmada';
    case Cancelada = 'cancelada';

    public function getLabel(): string
    {
        return match ($this) {
            self::Solicitada => 'Aguardando aprovação',
            self::Confirmada => 'Confirmada',
            self::Cancelada => 'Cancelada',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Solicitada => 'warning',
            self::Confirmada => 'success',
            self::Cancelada => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Solicitada => 'heroicon-o-clock',
            self::Confirmada => 'heroicon-o-check-circle',
            self::Cancelada => 'heroicon-o-x-circle',
        };
    }

    /**
     * Reservas que ocupam o espaço no horário (e portanto bloqueiam outras). Uma solicitação ainda não aprovada
     * também segura o horário, para dois pedidos não disputarem o mesmo auditório.
     */
    public function ocupaOEspaco(): bool
    {
        return $this !== self::Cancelada;
    }
}

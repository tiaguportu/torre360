<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum StatusRematricula: string implements HasColor, HasIcon, HasLabel
{
    case Iniciada = 'iniciada';
    case DadosConfirmados = 'dados_confirmados';
    case AguardandoAssinatura = 'aguardando_assinatura';
    case Confirmada = 'confirmada';
    case Cancelada = 'cancelada';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Iniciada => 'Iniciada',
            self::DadosConfirmados => 'Dados Confirmados',
            self::AguardandoAssinatura => 'Aguardando Assinatura do Contrato',
            self::Confirmada => 'Rematrícula Confirmada',
            self::Cancelada => 'Cancelada',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Iniciada => 'gray',
            self::DadosConfirmados => 'info',
            self::AguardandoAssinatura => 'warning',
            self::Confirmada => 'success',
            self::Cancelada => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Iniciada => 'heroicon-m-play',
            self::DadosConfirmados => 'heroicon-m-user-circle',
            self::AguardandoAssinatura => 'heroicon-m-pencil-square',
            self::Confirmada => 'heroicon-m-check-badge',
            self::Cancelada => 'heroicon-m-x-circle',
        };
    }
}

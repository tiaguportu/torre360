<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum StatusAcordoInadimplencia: string implements HasColor, HasIcon, HasLabel
{
    case Simulado = 'simulado';
    case AguardandoAceite = 'aguardando_aceite';
    case Ativo = 'ativo';
    case Cumprido = 'cumprido';
    case Quebrado = 'quebrado';
    case Cancelado = 'cancelado';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Simulado => 'Simulado',
            self::AguardandoAceite => 'Aguardando Aceite da Família',
            self::Ativo => 'Ativo / Em Pagamento',
            self::Cumprido => 'Cumprido / Quitado',
            self::Quebrado => 'Quebrado (Inadimplente)',
            self::Cancelado => 'Cancelado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Simulado => 'gray',
            self::AguardandoAceite => 'warning',
            self::Ativo => 'info',
            self::Cumprido => 'success',
            self::Quebrado => 'danger',
            self::Cancelado => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Simulado => 'heroicon-o-calculator',
            self::AguardandoAceite => 'heroicon-o-clock',
            self::Ativo => 'heroicon-o-arrow-path',
            self::Cumprido => 'heroicon-o-check-circle',
            self::Quebrado => 'heroicon-o-exclamation-triangle',
            self::Cancelado => 'heroicon-o-x-circle',
        };
    }
}

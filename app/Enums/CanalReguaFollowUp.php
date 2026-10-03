<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum CanalReguaFollowUp: string implements HasColor, HasIcon, HasLabel
{
    case Email = 'email';
    case NotificacaoSistema = 'notificacao_sistema';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Email => 'E-mail para a Família',
            self::NotificacaoSistema => 'Alerta no Painel (Sininho) para Consultor',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Email => 'info',
            self::NotificacaoSistema => 'warning',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Email => 'heroicon-m-envelope',
            self::NotificacaoSistema => 'heroicon-m-bell-alert',
        };
    }
}

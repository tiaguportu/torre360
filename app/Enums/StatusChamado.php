<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum StatusChamado: string implements HasColor, HasIcon, HasLabel
{
    case Aberto = 'aberto';
    case EmAndamento = 'em_andamento';
    case AguardandoSolicitante = 'aguardando_solicitante';
    case Resolvido = 'resolvido';
    case Fechado = 'fechado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Aberto => 'Aberto',
            self::EmAndamento => 'Em Andamento',
            self::AguardandoSolicitante => 'Aguardando Resposta da Família',
            self::Resolvido => 'Resolvido',
            self::Fechado => 'Encerrado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Aberto => 'info',
            self::EmAndamento => 'warning',
            self::AguardandoSolicitante => 'purple',
            self::Resolvido => 'success',
            self::Fechado => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Aberto => 'heroicon-o-inbox',
            self::EmAndamento => 'heroicon-o-arrow-path',
            self::AguardandoSolicitante => 'heroicon-o-chat-bubble-left-ellipsis',
            self::Resolvido => 'heroicon-o-check-circle',
            self::Fechado => 'heroicon-o-lock-closed',
        };
    }
}

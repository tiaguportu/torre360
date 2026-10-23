<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum StatusCandidato: string implements HasColor, HasIcon, HasLabel
{
    case Novo = 'novo';
    case EmTriagem = 'em_triagem';
    case EntrevistaAgendada = 'entrevista_agendada';
    case Entrevistado = 'entrevistado';
    case BancoTalentos = 'banco_talentos';
    case Aprovado = 'aprovado';
    case Reprovado = 'reprovado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Novo => 'Novo',
            self::EmTriagem => 'Em triagem',
            self::EntrevistaAgendada => 'Entrevista agendada',
            self::Entrevistado => 'Entrevistado',
            self::BancoTalentos => 'Banco de talentos',
            self::Aprovado => 'Aprovado',
            self::Reprovado => 'Reprovado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Novo => 'info',
            self::EmTriagem => 'warning',
            self::EntrevistaAgendada => 'primary',
            self::Entrevistado => 'purple',
            self::BancoTalentos => 'gray',
            self::Aprovado => 'success',
            self::Reprovado => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Novo => 'heroicon-o-sparkles',
            self::EmTriagem => 'heroicon-o-funnel',
            self::EntrevistaAgendada => 'heroicon-o-calendar',
            self::Entrevistado => 'heroicon-o-chat-bubble-bottom-center-text',
            self::BancoTalentos => 'heroicon-o-archive-box',
            self::Aprovado => 'heroicon-o-check-badge',
            self::Reprovado => 'heroicon-o-x-circle',
        };
    }
}

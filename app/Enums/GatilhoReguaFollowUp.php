<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum GatilhoReguaFollowUp: string implements HasColor, HasIcon, HasLabel
{
    case LeadCriado = 'lead_criado';
    case VisitaLembrete = 'visita_lembrete';
    case VisitaRealizada = 'visita_realizada';
    case VisitaFaltou = 'visita_faltou';
    case LeadEstagnado = 'lead_estagnado';
    case ContatoAtrasado = 'contato_atrasado';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::LeadCriado => 'Novo Lead Cadastrado (Boas-vindas)',
            self::VisitaLembrete => 'Lembrete de Visita Agendada (D-X)',
            self::VisitaRealizada => 'Pós-Visita Realizada (Agradecimento)',
            self::VisitaFaltou => 'Recuperação de Falta na Visita (No-Show)',
            self::LeadEstagnado => 'Lead Estagnado (Sem Interação há X dias)',
            self::ContatoAtrasado => 'Retorno de Contato Atrasado há X dias',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::LeadCriado => 'info',
            self::VisitaLembrete => 'warning',
            self::VisitaRealizada => 'success',
            self::VisitaFaltou => 'danger',
            self::LeadEstagnado => 'danger',
            self::ContatoAtrasado => 'amber',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::LeadCriado => 'heroicon-m-user-plus',
            self::VisitaLembrete => 'heroicon-m-calendar-days',
            self::VisitaRealizada => 'heroicon-m-check-badge',
            self::VisitaFaltou => 'heroicon-m-x-circle',
            self::LeadEstagnado => 'heroicon-m-clock',
            self::ContatoAtrasado => 'heroicon-m-exclamation-triangle',
        };
    }
}

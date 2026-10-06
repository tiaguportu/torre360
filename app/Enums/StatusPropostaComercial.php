<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum StatusPropostaComercial: string implements HasColor, HasIcon, HasLabel
{
    case Rascunho = 'rascunho';
    case AprovadaAutomatica = 'aprovada_automatica';
    case AguardandoAprovacao = 'aguardando_aprovacao';
    case Aprovada = 'aprovada';
    case Recusada = 'recusada';
    case AceitaPelaFamilia = 'aceita_pela_familia';
    case Convertida = 'convertida';
    case Expirada = 'expirada';
    case Cancelada = 'cancelada';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Rascunho => 'Rascunho',
            self::AprovadaAutomatica => 'Aprovada (Alçada Consultor)',
            self::AguardandoAprovacao => 'Aguardando Aprovação',
            self::Aprovada => 'Aprovada pela Alçada',
            self::Recusada => 'Recusada',
            self::AceitaPelaFamilia => 'Aceita pela Família',
            self::Convertida => 'Convertida em Matrícula',
            self::Expirada => 'Expirada',
            self::Cancelada => 'Cancelada',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Rascunho => 'gray',
            self::AprovadaAutomatica, self::Aprovada => 'success',
            self::AguardandoAprovacao => 'warning',
            self::Recusada => 'danger',
            self::AceitaPelaFamilia, self::Convertida => 'info',
            self::Expirada, self::Cancelada => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Rascunho => 'heroicon-m-document',
            self::AprovadaAutomatica, self::Aprovada => 'heroicon-m-check-badge',
            self::AguardandoAprovacao => 'heroicon-m-clock',
            self::Recusada => 'heroicon-m-x-circle',
            self::AceitaPelaFamilia => 'heroicon-m-hand-thumb-up',
            self::Convertida => 'heroicon-m-academic-cap',
            self::Expirada => 'heroicon-m-calendar-days',
            self::Cancelada => 'heroicon-m-no-symbol',
        };
    }
}

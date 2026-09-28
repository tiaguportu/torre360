<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum StatusSolicitacaoDocumento: string implements HasColor, HasIcon, HasLabel
{
    case Solicitado = 'solicitado';
    case EmAnalise = 'em_analise';
    case Disponivel = 'disponivel';
    case Rejeitado = 'rejeitado';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Solicitado => 'Solicitado',
            self::EmAnalise => 'Em Análise',
            self::Disponivel => 'Disponível para Download',
            self::Rejeitado => 'Rejeitado / Recusado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Solicitado => 'warning',
            self::EmAnalise => 'info',
            self::Disponivel => 'success',
            self::Rejeitado => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Solicitado => 'heroicon-m-clock',
            self::EmAnalise => 'heroicon-m-arrow-path',
            self::Disponivel => 'heroicon-m-check-circle',
            self::Rejeitado => 'heroicon-m-x-circle',
        };
    }
}

<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum SituacaoDocumento: string implements HasColor, HasIcon, HasLabel
{
    case EM_ANALISE = 'em_analise';
    case VERIFICADO = 'verificado';
    case REJEITADO = 'rejeitado';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::EM_ANALISE => 'Em Análise',
            self::VERIFICADO => 'Verificado',
            self::REJEITADO => 'Rejeitado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::EM_ANALISE => 'warning',
            self::VERIFICADO => 'success',
            self::REJEITADO => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::EM_ANALISE => 'heroicon-m-eye',
            self::VERIFICADO => 'heroicon-m-check-circle',
            self::REJEITADO => 'heroicon-m-x-circle',
        };
    }

    /**
     * Define as transições permitidas para a State Machine.
     */
    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::EM_ANALISE => in_array($target, [self::VERIFICADO, self::REJEITADO]),
            self::VERIFICADO => in_array($target, [self::EM_ANALISE, self::REJEITADO]),
            self::REJEITADO => in_array($target, [self::EM_ANALISE, self::VERIFICADO]),
        };
    }
}

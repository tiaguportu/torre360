<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum SituacaoDocumento: string implements HasColor, HasIcon, HasLabel
{
    case PENDENTE = 'pendente';
    case EM_ANALISE = 'em_analise';
    case APROVADO = 'aprovado';
    case REJEITADO = 'rejeitado';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PENDENTE => 'Pendente',
            self::EM_ANALISE => 'Em Análise',
            self::APROVADO => 'Aprovado',
            self::REJEITADO => 'Rejeitado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PENDENTE => 'gray',
            self::EM_ANALISE => 'warning',
            self::APROVADO => 'success',
            self::REJEITADO => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::PENDENTE => 'heroicon-m-clock',
            self::EM_ANALISE => 'heroicon-m-eye',
            self::APROVADO => 'heroicon-m-check-circle',
            self::REJEITADO => 'heroicon-m-x-circle',
        };
    }

    /**
     * Define as transições permitidas para a State Machine.
     */
    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::PENDENTE => in_array($target, [self::EM_ANALISE, self::APROVADO, self::REJEITADO]),
            self::EM_ANALISE => in_array($target, [self::APROVADO, self::REJEITADO]),
            self::APROVADO => in_array($target, [self::EM_ANALISE, self::REJEITADO]),
            self::REJEITADO => in_array($target, [self::PENDENTE, self::EM_ANALISE]),
        };
    }
}

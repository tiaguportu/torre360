<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum StatusCancelamentoMatricula: string implements HasColor, HasIcon, HasLabel
{
    case Solicitado = 'solicitado';
    case Aprovado = 'aprovado';
    case Concluido = 'concluido';
    case Recusado = 'recusado';
    case Retirado = 'retirado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Solicitado => 'Aguardando a secretaria',
            self::Aprovado => 'Aprovado (distrato)',
            self::Concluido => 'Concluído',
            self::Recusado => 'Recusado',
            self::Retirado => 'Retirado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Solicitado => 'warning',
            self::Aprovado => 'info',
            self::Concluido => 'success',
            self::Recusado => 'danger',
            self::Retirado => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Solicitado => 'heroicon-o-clock',
            self::Aprovado => 'heroicon-o-document-check',
            self::Concluido => 'heroicon-o-check-circle',
            self::Recusado => 'heroicon-o-x-circle',
            self::Retirado => 'heroicon-o-arrow-uturn-left',
        };
    }

    /**
     * Pedido ainda em andamento: ocupa a matrícula (não se abre outro) e a trava contra duplicidade.
     */
    public function estaAberto(): bool
    {
        return in_array($this, [self::Solicitado, self::Aprovado], true);
    }
}

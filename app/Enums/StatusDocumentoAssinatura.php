<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum StatusDocumentoAssinatura: string implements HasColor, HasIcon, HasLabel
{
    case Rascunho = 'rascunho';
    case Enviado = 'enviado';
    case Assinado = 'assinado';
    case Recusado = 'recusado';
    case Cancelado = 'cancelado';
    case Expirado = 'expirado';
    case Erro = 'erro';

    public function getLabel(): string
    {
        return match ($this) {
            self::Rascunho => 'Rascunho',
            self::Enviado => 'Aguardando assinaturas',
            self::Assinado => 'Assinado',
            self::Recusado => 'Recusado',
            self::Cancelado => 'Cancelado',
            self::Expirado => 'Expirado',
            self::Erro => 'Erro no envio',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Rascunho => 'gray',
            self::Enviado => 'warning',
            self::Assinado => 'success',
            self::Recusado, self::Erro => 'danger',
            self::Cancelado => 'gray',
            self::Expirado => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Rascunho => 'heroicon-o-pencil',
            self::Enviado => 'heroicon-o-clock',
            self::Assinado => 'heroicon-o-check-circle',
            self::Recusado => 'heroicon-o-hand-raised',
            self::Cancelado => 'heroicon-o-x-circle',
            self::Expirado => 'heroicon-o-calendar-days',
            self::Erro => 'heroicon-o-exclamation-triangle',
        };
    }

    /**
     * Ainda dá para mexer: o documento não foi encerrado.
     */
    public function emAndamento(): bool
    {
        return in_array($this, [self::Rascunho, self::Enviado, self::Erro], true);
    }
}

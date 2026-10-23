<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum StatusCasoFrequencia: string implements HasColor, HasIcon, HasLabel
{
    case Aberto = 'aberto';
    case EmContato = 'em_contato';
    case Notificado = 'notificado';
    case Encerrado = 'encerrado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Aberto => 'Aberto',
            self::EmContato => 'Em contato com a família',
            self::Notificado => 'Conselho Tutelar notificado',
            self::Encerrado => 'Encerrado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Aberto => 'danger',
            self::EmContato => 'warning',
            self::Notificado => 'info',
            self::Encerrado => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Aberto => 'heroicon-o-exclamation-triangle',
            self::EmContato => 'heroicon-o-phone',
            self::Notificado => 'heroicon-o-paper-airplane',
            self::Encerrado => 'heroicon-o-check-circle',
        };
    }

    /**
     * O caso ainda pede ação da escola.
     */
    public function estaAberto(): bool
    {
        return $this !== self::Encerrado;
    }
}

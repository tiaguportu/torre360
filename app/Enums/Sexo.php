<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum Sexo: string implements HasColor, HasIcon, HasLabel
{
    case FEMININO = 'feminino';
    case MASCULINO = 'masculino';
    case NAO_DECLARADO = 'nao_declarado';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::FEMININO => 'Feminino',
            self::MASCULINO => 'Masculino',
            self::NAO_DECLARADO => 'Não declarado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::FEMININO => 'danger',
            self::MASCULINO => 'info',
            self::NAO_DECLARADO => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::FEMININO => 'heroicon-m-user',
            self::MASCULINO => 'heroicon-m-user',
            self::NAO_DECLARADO => 'heroicon-m-user-minus',
        };
    }
}

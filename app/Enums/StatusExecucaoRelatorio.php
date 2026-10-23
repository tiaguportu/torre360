<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusExecucaoRelatorio: string implements HasColor, HasLabel
{
    case Sucesso = 'sucesso';
    case Erro = 'erro';

    public function getLabel(): string
    {
        return match ($this) {
            self::Sucesso => 'Gerado',
            self::Erro => 'Com erro',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Sucesso => 'success',
            self::Erro => 'danger',
        };
    }
}

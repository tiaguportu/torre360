<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum OrigemRegistroPonto: string implements HasLabel
{
    case Web = 'web';
    case Mobile = 'mobile';

    public function getLabel(): string
    {
        return match ($this) {
            self::Web => 'Navegador',
            self::Mobile => 'Aplicativo',
        };
    }
}

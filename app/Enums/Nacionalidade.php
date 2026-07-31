<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Nacionalidade: string implements HasLabel
{
    case BRASILEIRA = '1';
    case BRASILEIRA_EXTERIOR_OU_NATURALIZADO = '2';
    case ESTRANGEIRA = '3';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::BRASILEIRA => 'Brasileira',
            self::BRASILEIRA_EXTERIOR_OU_NATURALIZADO => 'Brasileira - nascido no Exterior ou Naturalizado',
            self::ESTRANGEIRA => 'Estrangeira',
        };
    }
}

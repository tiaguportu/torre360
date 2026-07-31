<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CorRaca: string implements HasLabel
{
    case NAO_DECLARADA = '0';
    case BRANCA = '1';
    case PRETA = '2';
    case PARDA = '3';
    case AMARELA = '4';
    case INDIGENA = '5';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::NAO_DECLARADA => 'Não Declarada',
            self::BRANCA => 'Branca',
            self::PRETA => 'Preta',
            self::PARDA => 'Parda',
            self::AMARELA => 'Amarela',
            self::INDIGENA => 'Indígena',
        };
    }
}

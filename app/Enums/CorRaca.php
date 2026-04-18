<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CorRaca: string implements HasLabel
{
    case BRANCA = 'branca';
    case PRETA = 'preta';
    case PARDA = 'parda';
    case AMARELA = 'amarela';
    case INDIGENA = 'indigena';
    case NAO_DECLARADO = 'nao_declarado';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::BRANCA => 'Branca',
            self::PRETA => 'Preta',
            self::PARDA => 'Parda',
            self::AMARELA => 'Amarela',
            self::INDIGENA => 'Indígena',
            self::NAO_DECLARADO => 'Não declarado',
        };
    }
}

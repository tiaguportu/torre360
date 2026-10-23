<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MeioContatoFamilia: string implements HasLabel
{
    case Telefone = 'telefone';
    case Whatsapp = 'whatsapp';
    case Email = 'email';
    case Presencial = 'presencial';
    case Carta = 'carta';

    public function getLabel(): string
    {
        return match ($this) {
            self::Telefone => 'Telefone',
            self::Whatsapp => 'WhatsApp',
            self::Email => 'E-mail',
            self::Presencial => 'Conversa presencial',
            self::Carta => 'Carta ou bilhete',
        };
    }
}

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

    public static function tryFromValue(mixed $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof self) {
            return $value;
        }

        $str = (string) $value;

        if ($enum = self::tryFrom($str)) {
            return $enum;
        }

        return match (mb_strtolower($str)) {
            '0', 'nao_declarada', 'nao_declarado', 'não declarada', 'não declarado' => self::NAO_DECLARADA,
            '1', 'branca', 'branc' => self::BRANCA,
            '2', 'preta', 'pret' => self::PRETA,
            '3', 'parda', 'pard' => self::PARDA,
            '4', 'amarela', 'amar' => self::AMARELA,
            '5', 'indigena', 'indígena', 'indig' => self::INDIGENA,
            default => null,
        };
    }

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

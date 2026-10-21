<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TipoRefeicaoCardapio: string implements HasLabel
{
    case CafeDaManha = 'cafe_manha';
    case LancheDaManha = 'lanche_manha';
    case Almoco = 'almoco';
    case LancheDaTarde = 'lanche_tarde';
    case Jantar = 'jantar';

    public function getLabel(): string
    {
        return match ($this) {
            self::CafeDaManha => 'Café da manhã',
            self::LancheDaManha => 'Lanche da manhã',
            self::Almoco => 'Almoço',
            self::LancheDaTarde => 'Lanche da tarde',
            self::Jantar => 'Jantar',
        };
    }

    /**
     * Posição da refeição ao longo do dia, para ordenar a exibição.
     */
    public function ordem(): int
    {
        return match ($this) {
            self::CafeDaManha => 1,
            self::LancheDaManha => 2,
            self::Almoco => 3,
            self::LancheDaTarde => 4,
            self::Jantar => 5,
        };
    }
}

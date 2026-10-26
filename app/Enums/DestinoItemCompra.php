<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Para onde vai o item comprado ao ser recebido (Onda 38): o consumo e o serviço só geram a conta a pagar; o item de estoque dá
 * entrada no saldo; o bem durável vira cadastro no patrimônio.
 */
enum DestinoItemCompra: string implements HasLabel
{
    case Consumo = 'consumo';
    case Estoque = 'estoque';
    case Patrimonio = 'patrimonio';
    case Servico = 'servico';

    public function getLabel(): string
    {
        return match ($this) {
            self::Consumo => 'Consumo direto',
            self::Estoque => 'Entra no estoque',
            self::Patrimonio => 'Bem durável (patrimônio)',
            self::Servico => 'Serviço',
        };
    }
}

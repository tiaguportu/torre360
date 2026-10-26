<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Situação do processo de compra (Onda 38). A requisição é quem carrega o status do processo inteiro: rascunho, cotação, aprovação do
 * pedido e recebimento.
 */
enum StatusRequisicaoCompra: string implements HasColor, HasLabel
{
    case Rascunho = 'rascunho';
    case EmCotacao = 'em_cotacao';
    case AguardandoAprovacao = 'aguardando_aprovacao';
    case Aprovada = 'aprovada';
    case RecebidaParcial = 'recebida_parcial';
    case Recebida = 'recebida';
    case Recusada = 'recusada';
    case Cancelada = 'cancelada';

    public function getLabel(): string
    {
        return match ($this) {
            self::Rascunho => 'Rascunho',
            self::EmCotacao => 'Em cotação',
            self::AguardandoAprovacao => 'Aguardando aprovação',
            self::Aprovada => 'Aprovada, aguardando entrega',
            self::RecebidaParcial => 'Recebida em parte',
            self::Recebida => 'Recebida',
            self::Recusada => 'Recusada',
            self::Cancelada => 'Cancelada',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Rascunho => 'gray',
            self::EmCotacao => 'info',
            self::AguardandoAprovacao => 'warning',
            self::Aprovada => 'primary',
            self::RecebidaParcial => 'warning',
            self::Recebida => 'success',
            self::Recusada, self::Cancelada => 'danger',
        };
    }

    public function encerrada(): bool
    {
        return in_array($this, [self::Recebida, self::Recusada, self::Cancelada], true);
    }
}

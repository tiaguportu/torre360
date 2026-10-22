<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MotivoDesligamentoFuncionario: string implements HasLabel
{
    case PedidoDemissao = 'pedido_demissao';
    case DispensaSemJustaCausa = 'dispensa_sem_justa_causa';
    case JustaCausa = 'justa_causa';
    case TerminoContrato = 'termino_contrato';
    case Acordo = 'acordo';
    case Aposentadoria = 'aposentadoria';
    case Outro = 'outro';

    public function getLabel(): string
    {
        return match ($this) {
            self::PedidoDemissao => 'Pedido de demissão',
            self::DispensaSemJustaCausa => 'Dispensa sem justa causa',
            self::JustaCausa => 'Dispensa por justa causa',
            self::TerminoContrato => 'Término de contrato por prazo determinado',
            self::Acordo => 'Acordo entre as partes (CLT, art. 484-A)',
            self::Aposentadoria => 'Aposentadoria',
            self::Outro => 'Outro motivo',
        };
    }
}

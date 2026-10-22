<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MotivoCancelamentoMatricula: string implements HasLabel
{
    case MudancaCidade = 'mudanca_cidade';
    case MudancaEscola = 'mudanca_escola';
    case DificuldadeFinanceira = 'dificuldade_financeira';
    case Insatisfacao = 'insatisfacao';
    case Saude = 'saude';
    case Outro = 'outro';

    public function getLabel(): string
    {
        return match ($this) {
            self::MudancaCidade => 'Mudança de cidade',
            self::MudancaEscola => 'Transferência para outra escola',
            self::DificuldadeFinanceira => 'Dificuldade financeira',
            self::Insatisfacao => 'Insatisfação com a escola',
            self::Saude => 'Motivo de saúde',
            self::Outro => 'Outro motivo',
        };
    }
}

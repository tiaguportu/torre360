<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TipoRelatorioPreceptoria: string implements HasLabel
{
    case AnaliseGeral = 'Analise Geral';
    case PlanoPessoalMelhora = 'Plano Pessoal de Melhora';
    case AcompanhamentoAcademico = 'Acompanhamento Acadêmico';
    case FeedbackComportamental = 'Feedback Comportamental';
    case Outros = 'Outros';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::AnaliseGeral => 'Análise Geral',
            self::PlanoPessoalMelhora => 'Plano Pessoal de Melhora',
            self::AcompanhamentoAcademico => 'Acompanhamento Acadêmico',
            self::FeedbackComportamental => 'Feedback Comportamental',
            self::Outros => 'Outros',
        };
    }
}

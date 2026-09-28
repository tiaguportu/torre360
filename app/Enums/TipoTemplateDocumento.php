<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum TipoTemplateDocumento: string implements HasColor, HasIcon, HasLabel
{
    case DeclaracaoMatricula = 'declaracao_matricula';
    case DeclaracaoFrequencia = 'declaracao_frequencia';
    case DeclaracaoQuitacao = 'declaracao_quitacao';
    case HistoricoEscolar = 'historico_escolar';
    case Personalizado = 'personalizado';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::DeclaracaoMatricula => 'Declaração de Matrícula',
            self::DeclaracaoFrequencia => 'Declaração de Frequência',
            self::DeclaracaoQuitacao => 'Declaração de Quitação de Débitos',
            self::HistoricoEscolar => 'Histórico Escolar',
            self::Personalizado => 'Documento Personalizado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DeclaracaoMatricula => 'primary',
            self::DeclaracaoFrequencia => 'info',
            self::DeclaracaoQuitacao => 'success',
            self::HistoricoEscolar => 'warning',
            self::Personalizado => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::DeclaracaoMatricula => 'heroicon-m-academic-cap',
            self::DeclaracaoFrequencia => 'heroicon-m-calendar-days',
            self::DeclaracaoQuitacao => 'heroicon-m-banknotes',
            self::HistoricoEscolar => 'heroicon-m-document-text',
            self::Personalizado => 'heroicon-m-document-duplicate',
        };
    }
}

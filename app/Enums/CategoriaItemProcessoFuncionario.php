<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CategoriaItemProcessoFuncionario: string implements HasColor, HasLabel
{
    case Documento = 'documento';
    case Exame = 'exame';
    case Contrato = 'contrato';
    case Acesso = 'acesso';
    case Equipamento = 'equipamento';
    case Treinamento = 'treinamento';
    case Financeiro = 'financeiro';
    case Outro = 'outro';

    public function getLabel(): string
    {
        return match ($this) {
            self::Documento => 'Documentos',
            self::Exame => 'Exame médico',
            self::Contrato => 'Contrato e registro',
            self::Acesso => 'Acessos',
            self::Equipamento => 'Equipamentos',
            self::Treinamento => 'Treinamento',
            self::Financeiro => 'Financeiro',
            self::Outro => 'Outros',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Documento => 'info',
            self::Exame => 'danger',
            self::Contrato => 'primary',
            self::Acesso => 'warning',
            self::Equipamento => 'gray',
            self::Treinamento => 'success',
            self::Financeiro => 'primary',
            self::Outro => 'gray',
        };
    }
}

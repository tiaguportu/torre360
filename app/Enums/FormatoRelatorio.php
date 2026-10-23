<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Formato do arquivo de um relatório configurável (Onda 72). O CSV serve à planilha e à análise; o PDF, à leitura e à
 * impressão, com limite de linhas menor.
 */
enum FormatoRelatorio: string implements HasLabel
{
    case Csv = 'csv';
    case Pdf = 'pdf';

    public function getLabel(): string
    {
        return match ($this) {
            self::Csv => 'Planilha (CSV)',
            self::Pdf => 'Documento (PDF)',
        };
    }

    public function mime(): string
    {
        return match ($this) {
            self::Csv => 'text/csv; charset=UTF-8',
            self::Pdf => 'application/pdf',
        };
    }
}

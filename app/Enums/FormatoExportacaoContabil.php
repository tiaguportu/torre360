<?php

namespace App\Enums;

use App\Services\Contabil\ExportacaoContabilService;
use Filament\Support\Contracts\HasLabel;

/**
 * Leiaute do arquivo entregue ao escritório (Onda 73). O CSV de lançamentos serve à maioria dos sistemas contábeis, que
 * importam partidas dobradas por planilha; o balancete serve à conferência. Um leiaute proprietário (Domínio, Alterdata,
 * Fortes...) entra como mais um caso, com o seu gerador em {@see ExportacaoContabilService::gerarArquivo()}.
 */
enum FormatoExportacaoContabil: string implements HasLabel
{
    case CsvLancamentos = 'csv_lancamentos';
    case CsvBalancete = 'csv_balancete';

    public function getLabel(): string
    {
        return match ($this) {
            self::CsvLancamentos => 'Lançamentos contábeis (CSV, partidas dobradas)',
            self::CsvBalancete => 'Balancete de conferência (CSV, total por conta)',
        };
    }

    public function extensao(): string
    {
        return 'csv';
    }
}

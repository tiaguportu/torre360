<?php

namespace App\Console\Commands;

use App\Services\ContaRecorrenteService;
use Illuminate\Console\Command;

class GerarContasRecorrentesCommand extends Command
{
    protected $signature = 'financeiro:gerar-contas-recorrentes';

    protected $description = 'Gera as contas a pagar das despesas recorrentes cujo vencimento entrou na janela de antecedência';

    public function handle(ContaRecorrenteService $servico): int
    {
        $resultado = $servico->gerar();

        $this->info("{$resultado['geradas']} conta(s) a pagar gerada(s); {$resultado['ja_existiam']} já existia(m).");

        return self::SUCCESS;
    }
}

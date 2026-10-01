<?php

namespace App\Console\Commands;

use App\Models\ContaPagar;
use Illuminate\Console\Command;

class AtualizarContasPagarAtrasadasCommand extends Command
{
    protected $signature = 'financeiro:atualizar-contas-pagar-atrasadas';

    protected $description = 'Marca como Atrasado as contas a pagar pendentes cujo vencimento já passou';

    public function handle(): int
    {
        $total = ContaPagar::atualizarAtrasadas();

        $this->info("{$total} conta(s) a pagar marcada(s) como atrasada(s).");

        return self::SUCCESS;
    }
}

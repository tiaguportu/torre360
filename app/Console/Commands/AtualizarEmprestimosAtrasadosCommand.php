<?php

namespace App\Console\Commands;

use App\Models\Emprestimo;
use Illuminate\Console\Command;

class AtualizarEmprestimosAtrasadosCommand extends Command
{
    protected $signature = 'biblioteca:atualizar-emprestimos-atrasados';

    protected $description = 'Marca como Atrasado os empréstimos cuja devolução prevista já passou';

    public function handle(): int
    {
        $total = Emprestimo::atualizarAtrasados();

        $this->info("{$total} empréstimo(s) marcado(s) como atrasado(s).");

        return self::SUCCESS;
    }
}

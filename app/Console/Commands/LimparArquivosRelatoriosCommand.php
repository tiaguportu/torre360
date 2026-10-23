<?php

namespace App\Console\Commands;

use App\Services\Relatorios\RelatorioExecutorService;
use Illuminate\Console\Command;

class LimparArquivosRelatoriosCommand extends Command
{
    protected $signature = 'relatorios:limpar-arquivos';

    protected $description = 'Apaga os arquivos de relatórios cuja retenção acabou (o registro da execução permanece)';

    public function handle(RelatorioExecutorService $servico): int
    {
        $this->info($servico->limparArquivosVencidos().' arquivo(s) apagado(s).');

        return self::SUCCESS;
    }
}

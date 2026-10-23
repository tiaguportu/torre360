<?php

namespace App\Console\Commands;

use App\Services\Relatorios\RelatorioExecutorService;
use Illuminate\Console\Command;

class ExecutarRelatoriosAgendadosCommand extends Command
{
    protected $signature = 'relatorios:executar-agendados {--limite= : Máximo de relatórios por rodada}';

    protected $description = 'Gera os relatórios agendados cuja hora chegou e avisa os destinatários (o e-mail leva só o link)';

    public function handle(RelatorioExecutorService $servico): int
    {
        $resumo = $servico->executarAgendados(null, filled($this->option('limite')) ? (int) $this->option('limite') : null);

        $this->info(sprintf('%d relatório(s) executado(s), %d com erro, %d agendamento(s) desligado(s).', $resumo['executados'], $resumo['com_erro'], $resumo['desligados']));

        return self::SUCCESS;
    }
}

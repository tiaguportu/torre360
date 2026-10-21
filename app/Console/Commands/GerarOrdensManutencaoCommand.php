<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ManutencaoPreventivaService;
use Illuminate\Console\Command;

class GerarOrdensManutencaoCommand extends Command
{
    protected $signature = 'manutencao:gerar-ordens';

    protected $description = 'Abre as ordens de serviço dos planos de manutenção preventiva que entraram na janela de antecedência e sincroniza as concluídas';

    public function handle(ManutencaoPreventivaService $servico): int
    {
        $sincronizadas = $servico->sincronizarComOrdensDeServico();
        $abertas = $servico->gerarOrdensPendentes();

        $this->info("Manutenção preventiva: {$abertas} ordem(ns) aberta(s), {$sincronizadas} execução(ões) sincronizada(s).");

        return self::SUCCESS;
    }
}

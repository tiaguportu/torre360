<?php

namespace App\Console\Commands;

use App\Services\LivroCapaService;
use Illuminate\Console\Command;

class LimparCapasPendentesCommand extends Command
{
    protected $signature = 'biblioteca:limpar-capas-pendentes {--horas=48 : Apaga capas pendentes mais antigas que este prazo}';

    protected $description = 'Remove as capas baixadas pela busca por ISBN que nunca foram salvas junto a um livro';

    public function handle(LivroCapaService $capas): int
    {
        $apagados = $capas->limparPendentes((int) $this->option('horas'));

        $this->info("{$apagados} capa(s) pendente(s) removida(s).");

        return self::SUCCESS;
    }
}

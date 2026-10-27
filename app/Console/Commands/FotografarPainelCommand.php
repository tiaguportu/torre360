<?php

namespace App\Console\Commands;

use App\Services\Painel\PainelDirecaoService;
use Illuminate\Console\Command;

class FotografarPainelCommand extends Command
{
    protected $signature = 'painel:fotografar';

    protected $description = 'Guarda a fotografia diária dos indicadores do Painel da Direção e apaga as fotografias muito antigas';

    public function handle(PainelDirecaoService $painel): int
    {
        $gravadas = $painel->fotografar();
        $apagadas = $painel->limpar();

        $this->info("{$gravadas} fotografia(s) gravada(s); {$apagadas} antiga(s) apagada(s).");

        return self::SUCCESS;
    }
}

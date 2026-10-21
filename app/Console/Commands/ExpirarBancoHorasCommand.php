<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Ponto\BancoHorasService;
use Illuminate\Console\Command;

/**
 * Converte em "horas a pagar" os créditos do banco de horas cujo prazo de compensação venceu (CLT, art. 59, § 3º).
 */
class ExpirarBancoHorasCommand extends Command
{
    protected $signature = 'ponto:expirar-banco-horas';

    protected $description = 'Expira créditos de banco de horas fora do prazo de compensação e os marca como horas a pagar';

    public function handle(BancoHorasService $banco): int
    {
        $expirados = $banco->expirarVencidos();

        $this->info("{$expirados} crédito(s) expirado(s).");

        return self::SUCCESS;
    }
}

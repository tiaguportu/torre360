<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\LeadSlaService;
use Illuminate\Console\Command;

class VerificarSlaPrimeiraRespostaCommand extends Command
{
    protected $signature = 'crm:verificar-sla-estourado';

    protected $description = 'Verifica leads com SLA de primeira resposta estourado (considerando horário comercial) e notifica os consultores e a gestão';

    public function handle(LeadSlaService $slaService): int
    {
        $this->info('Iniciando verificação de SLA de 1ª resposta...');

        $detalhes = [];
        $notificados = $slaService->verificarENotificarEstouros($detalhes);

        $consultorQtd = $detalhes['consultor'] ?? 0;
        $gestaoQtd = $detalhes['gestao'] ?? 0;

        if ($notificados === 0) {
            $this->info('Nenhum novo estouro de SLA encontrado.');
        } else {
            $this->warn("Notificação de estouro de SLA enviada: {$consultorQtd} lead(s) para consultores e {$gestaoQtd} lead(s) sem consultor para a gestão.");
        }

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\AlertaLeadsService;
use App\Services\VisitaInteressadoService;
use Illuminate\Console\Command;

class NotificarInteressadosPendentesCommand extends Command
{
    protected $signature = 'crm:notificar-pendentes';

    protected $description = 'Notifica consultores sobre leads com contato atrasado, estagnados sem interação e visitas agendadas para as próximas 24h, e a gestão sobre leads parados ou sem consultor';

    public function handle(AlertaLeadsService $alertas): int
    {
        $lembretesVisita = VisitaInteressadoService::enviarLembretes();

        if ($lembretesVisita > 0) {
            $this->info("Lembretes de visita enviados: {$lembretesVisita}.");
        }

        $resultado = $alertas->executar();
        $notificados = $resultado['atrasados'] + $resultado['estagnados'];

        if ($notificados === 0 && $resultado['sem_consultor'] === 0) {
            $this->info('Nenhum lead pendente de contato, estagnado ou sem consultor encontrado.');

            return self::SUCCESS;
        }

        $this->info("Notificações enviadas para {$notificados} lead(s) ({$resultado['atrasados']} atrasado(s), {$resultado['estagnados']} estagnado(s)).");

        if ($resultado['escalonados'] > 0) {
            $this->info("{$resultado['escalonados']} lead(s) levados à gestão por estarem parados há muito tempo.");
        }

        if ($resultado['sem_consultor'] > 0) {
            $this->warn("{$resultado['sem_consultor']} lead(s) ativo(s) sem consultor responsável (resumo enviado à gestão).");
        }

        return self::SUCCESS;
    }
}

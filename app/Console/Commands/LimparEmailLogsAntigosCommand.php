<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\EmailLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Manutenção preventiva e retenção de histórico:
 * Expurga registros de logs de e-mails antigos (por padrão, mais de 365 dias / 1 ano),
 * liberando espaço em disco e mantendo a alta performance das consultas na tabela email_logs.
 */
class LimparEmailLogsAntigosCommand extends Command
{
    /**
     * O nome e a assinatura do comando no console.
     *
     * @var string
     */
    protected $signature = 'email:limpar-logs 
                            {--dias=365 : Quantidade de dias de retenção para considerar logs obsoletos (padrão: 365)}
                            {--dry-run : Apenas simula e exibe a quantidade de registros que seriam removidos}';

    /**
     * A descrição do comando no console.
     *
     * @var string
     */
    protected $description = 'Expurga logs de e-mails antigos com mais de 1 ano (365 dias) para otimização do banco de dados';

    public function handle(): int
    {
        $dias = (int) $this->option('dias');
        if ($dias <= 0) {
            $dias = 365;
        }

        $limite = now()->subDays($dias);

        $query = EmailLog::query()
            ->where(function ($q) use ($limite) {
                $q->where('sent_at', '<', $limite)
                    ->orWhere(fn ($sub) => $sub->whereNull('sent_at')->where('created_at', '<', $limite));
            });

        $totalElegivel = (clone $query)->count();

        if ($totalElegivel === 0) {
            $this->info("Nenhum log de e-mail com mais de {$dias} dias encontrado.");

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn("[DRY-RUN] {$totalElegivel} registro(s) de log de e-mail seriam removidos (mais de {$dias} dias).");

            return self::SUCCESS;
        }

        // Exclui em lotes de 500 registros para evitar travamento da tabela
        $removidos = 0;
        do {
            $ids = (clone $query)->limit(500)->pluck('id');
            if ($ids->isEmpty()) {
                break;
            }

            $afetados = EmailLog::query()->whereIn('id', $ids)->delete();
            $removidos += $afetados;
        } while ($ids->count() === 500);

        Log::info('Manutenção preventiva: logs de e-mails antigos expurgados', [
            'total_removidos' => $removidos,
            'dias_retencao' => $dias,
            'limite_data' => $limite->toDateTimeString(),
        ]);

        $this->info("{$removidos} registro(s) de log de e-mail antigos expurgados com sucesso (mais de {$dias} dias).");

        return self::SUCCESS;
    }
}

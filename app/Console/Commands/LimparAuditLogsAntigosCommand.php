<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Manutenção preventiva e conformidade com LGPD (Item M5):
 * Expurga registros de logs de auditoria (tabela audit_logs) mais antigos que a retenção definida
 * (padrão: 180 dias), garantindo descarte adequado de logs e desempenho do banco de dados.
 */
class LimparAuditLogsAntigosCommand extends Command
{
    /**
     * O nome e a assinatura do comando no console.
     *
     * @var string
     */
    protected $signature = 'audit:limpar-logs
                            {--dias=180 : Quantidade de dias de retenção para considerar logs obsoletos (padrão: 180)}
                            {--dry-run : Apenas simula e exibe a quantidade de registros que seriam removidos}';

    /**
     * A descrição do comando no console.
     *
     * @var string
     */
    protected $description = 'Expurga logs de auditoria (audit_logs) com mais de 180 dias para conformidade LGPD e otimização do banco';

    public function handle(): int
    {
        $dias = (int) $this->option('dias');
        if ($dias <= 0) {
            $dias = 180;
        }

        $limite = now()->subDays($dias);

        $query = AuditLog::query()->where('created_at', '<', $limite);

        $totalElegivel = (clone $query)->count();

        if ($totalElegivel === 0) {
            $this->info("Nenhum log de auditoria com mais de {$dias} dias encontrado.");

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn("[DRY-RUN] {$totalElegivel} registro(s) de log de auditoria seriam removidos (mais de {$dias} dias).");

            return self::SUCCESS;
        }

        // Exclui em lotes de 500 registros para evitar travamento da tabela
        $removidos = 0;
        do {
            $ids = (clone $query)->limit(500)->pluck('id');
            if ($ids->isEmpty()) {
                break;
            }

            $afetados = AuditLog::query()->whereIn('id', $ids)->delete();
            $removidos += $afetados;
        } while ($ids->count() === 500);

        Log::info('Manutenção preventiva: logs de auditoria antigos expurgados', [
            'total_removidos' => $removidos,
            'dias_retencao' => $dias,
            'limite_data' => $limite->toDateTimeString(),
        ]);

        $this->info("{$removidos} registro(s) de log de auditoria com mais de {$dias} dias foram removidos com sucesso.");

        return self::SUCCESS;
    }
}

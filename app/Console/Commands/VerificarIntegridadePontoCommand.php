<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PontoConfiguracao;
use App\Services\Ponto\PontoIntegridadeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Confere a cadeia de registros de ponto de cada unidade. Qualquer quebra (número faltando, encadeamento rompido,
 * conteúdo adulterado, registros removidos) é gravada como erro crítico e faz o comando falhar, para o agendador alertar.
 */
class VerificarIntegridadePontoCommand extends Command
{
    protected $signature = 'ponto:verificar-integridade {--unidade= : Verifica só esta unidade}';

    protected $description = 'Verifica a integridade da cadeia de registros de ponto (NSR e hash) de cada unidade';

    public function handle(PontoIntegridadeService $integridade): int
    {
        $configs = PontoConfiguracao::query()
            ->when($this->option('unidade'), fn ($q, $unidade) => $q->where('unidade_id', $unidade))
            ->get();

        $falhou = false;

        foreach ($configs as $config) {
            $resultado = $integridade->verificar($config->unidade_id);

            if ($resultado['ok']) {
                $this->info("Unidade {$config->unidade_id}: {$resultado['total']} registro(s) íntegros.");

                continue;
            }

            $falhou = true;
            $this->error("Unidade {$config->unidade_id}: INTEGRIDADE COMPROMETIDA ({$resultado['total']} registros verificados).");

            foreach ($resultado['quebras'] as $quebra) {
                $this->line(" - [{$quebra['tipo']}] ".($quebra['nsr'] !== null ? "NSR {$quebra['nsr']}: " : '').$quebra['detalhe']);
            }

            Log::critical('Integridade dos registros de ponto comprometida.', ['unidade_id' => $config->unidade_id, 'quebras' => $resultado['quebras']]);
        }

        return $falhou ? self::FAILURE : self::SUCCESS;
    }
}

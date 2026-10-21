<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\StatusOmrCorrecao;
use App\Models\OmrCorrecao;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class LimparUploadsTemporariosOmrCommand extends Command
{
    /**
     * O nome e a assinatura do comando no console.
     *
     * @var string
     */
    protected $signature = 'omr:limpar-temporarios {--dias=7 : Quantidade de dias para considerar arquivos temporários obsoletos}';

    /**
     * A descrição do comando.
     *
     * @var string
     */
    protected $description = 'Expurga uploads temporários e fotos de gabaritos temporárias (descartadas ou com falha) com mais de 7 dias';

    public function handle(): int
    {
        $dias = (int) $this->option('dias');
        if ($dias <= 0) {
            $dias = 7;
        }

        $limiteTimestamp = now()->subDays($dias)->timestamp;
        $limiteData = now()->subDays($dias);

        // 1. Expurga uploads temporários abandonados na pasta omr/uploads com mais de $dias dias
        $removidosUploads = 0;
        $arquivosUpload = Storage::disk('local')->files('omr/uploads');

        foreach ($arquivosUpload as $arquivo) {
            if (Storage::disk('local')->lastModified($arquivo) < $limiteTimestamp) {
                Storage::disk('local')->delete($arquivo);
                $removidosUploads++;
            }
        }

        // 2. Expurga imagens de correções transitórias (descartadas ou que falharam na leitura) com mais de $dias dias
        $correcoesTemporarias = OmrCorrecao::query()
            ->whereIn('status', [StatusOmrCorrecao::Descartada, StatusOmrCorrecao::Falha])
            ->where('created_at', '<', $limiteData)
            ->whereNotNull('arquivo_path')
            ->whereNotIn('arquivo_path', ['purgado_lgpd', 'expurgado_temp'])
            ->get();

        $removidosFotosCorrecoes = 0;
        foreach ($correcoesTemporarias as $correcao) {
            $path = (string) $correcao->arquivo_path;
            if (Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
                $removidosFotosCorrecoes++;
            }
            $correcao->update(['arquivo_path' => 'expurgado_temp']);
        }

        $mensagem = "Limpeza de arquivos temporários OMR concluída: {$removidosUploads} uploads e {$removidosFotosCorrecoes} fotos transitórias removidos (limite: {$dias} dias).";
        $this->info($mensagem);
        Log::info($mensagem);

        return self::SUCCESS;
    }
}

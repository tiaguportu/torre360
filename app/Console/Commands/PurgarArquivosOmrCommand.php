<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\OmrCorrecao;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PurgarArquivosOmrCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'omr:purgar-arquivos-antigos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purga fotos de gabarito antigas e temporárias conforme política de retenção LGPD';

    public function handle(): int
    {
        $diasRetencao = (int) config('omr.retencao_dias', 30);
        $limite = now()->subDays($diasRetencao);

        // 1. Purga as fotos de qualquer correção mais antiga que a retenção, inclusive as de leitura com falha ou
        // abandonadas na conferência: a foto só serve de registro e carrega a letra de menores. O resultado
        // (respostas, nota e auditoria) permanece na tabela.
        $correcoesAntigas = OmrCorrecao::where('created_at', '<', $limite)
            ->whereNotNull('arquivo_path')
            ->where('arquivo_path', '!=', 'purgado_lgpd')
            ->get();

        $removidos = 0;
        foreach ($correcoesAntigas as $c) {
            $path = (string) $c->arquivo_path;
            if (Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
                $removidos++;
            }
            $c->update(['arquivo_path' => 'purgado_lgpd']);
        }

        // 2. Limpa uploads temporários abandonados com mais de 24 horas
        $limiteTemp = now()->subDay()->timestamp;
        $arquivosTemp = Storage::disk('local')->files('omr/uploads');
        $removidosTemp = 0;

        foreach ($arquivosTemp as $arq) {
            if (Storage::disk('local')->lastModified($arq) < $limiteTemp) {
                Storage::disk('local')->delete($arq);
                $removidosTemp++;
            }
        }

        $this->info("Purga OMR concluída: {$removidos} fotos antigas e {$removidosTemp} uploads temporários removidos.");
        Log::info("Purga OMR executada: {$removidos} fotos antigas e {$removidosTemp} temporários excluídos.");

        return self::SUCCESS;
    }
}

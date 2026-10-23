<?php

namespace App\Console\Commands;

use App\Services\Documentos\AssinaturaDocumentoService;
use Illuminate\Console\Command;

class ReconciliarAssinaturasDocumentosCommand extends Command
{
    protected $signature = 'documentos:reconciliar-assinaturas {--limite=100 : Máximo de documentos consultados por execução}';

    protected $description = 'Consulta o Assinafy sobre os documentos avulsos aguardando assinatura, baixa o certificado que ainda não veio e expira o que passou do prazo';

    public function handle(AssinaturaDocumentoService $servico): int
    {
        $resumo = $servico->reconciliar(max(1, (int) $this->option('limite')));

        $this->info(sprintf(
            '%d documento(s) consultado(s), %d certificado(s) baixado(s), %d expirado(s), %d falha(s).',
            $resumo['consultados'],
            $resumo['certificados'],
            $resumo['expirados'],
            $resumo['falhas'],
        ));

        return self::SUCCESS;
    }
}

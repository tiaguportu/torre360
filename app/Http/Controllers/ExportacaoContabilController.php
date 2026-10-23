<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\LoteContabil;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baixa um arquivo da contabilidade já gerado (Onda 73). O arquivo leva todo o movimento financeiro, então só quem exporta
 * baixa; antes de entregar, o controlador confere se o conteúdo ainda tem a impressão digital registrada no lote.
 */
class ExportacaoContabilController extends Controller
{
    public function download(LoteContabil $lote): Response
    {
        abort_unless(auth()->user()?->can('Exportar:ExportacaoContabil'), 403, 'Sem permissão para baixar a exportação contábil.');

        $disco = Storage::disk('local');

        abort_unless($disco->exists($lote->arquivo_path), 404, 'O arquivo não está mais no servidor. Gere a exportação de novo.');

        $conteudo = $disco->get($lote->arquivo_path);

        abort_unless(hash_equals($lote->arquivo_hash, hash('sha256', $conteudo)), 409, 'O arquivo guardado foi alterado depois de gerado. Gere a exportação de novo.');

        activity('contabilidade')->performedOn($lote)->causedBy(auth()->user())->log('Arquivo da contabilidade baixado.');

        return response($conteudo, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$lote->nomeDoArquivo().'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

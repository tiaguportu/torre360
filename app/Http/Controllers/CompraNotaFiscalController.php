<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\RecebimentoCompra;
use App\Models\User;
use App\Support\TiposArquivo;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Entrega a nota fiscal de um recebimento de compra (disco privado). Quem pode abrir a requisição pode abrir a nota: o próprio
 * solicitante ou quem tem "Ver todas". O tipo é o detectado no arquivo; só imagem e PDF abrem no navegador, o resto baixa.
 */
class CompraNotaFiscalController extends Controller
{
    public function __invoke(RecebimentoCompra $recebimento): StreamedResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $recebimento->loadMissing('pedido.requisicao');
        abort_unless($recebimento->pedido && $recebimento->pedido->requisicao && Gate::forUser($user)->allows('view', $recebimento->pedido->requisicao), 403);

        $caminho = $recebimento->nota_fiscal_path;
        abort_if(blank($caminho) || ! Storage::disk('local')->exists($caminho), 404);

        $mime = (string) (Storage::disk('local')->mimeType($caminho) ?: 'application/octet-stream');
        $inline = in_array($mime, TiposArquivo::exibiveisNoNavegador(), true);

        return Storage::disk('local')->response($caminho, basename($caminho), [
            'Content-Type' => $mime,
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.basename($caminho).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}

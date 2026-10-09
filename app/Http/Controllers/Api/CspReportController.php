<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Recebe os relatos de violação da Content-Security-Policy (modo report-only) e os registra no log, uma vez por hora
 * para cada combinação diretiva + origem bloqueada + página, para a equipe ajustar a lista antes de ativar o bloqueio.
 *
 * É um endpoint público (o navegador envia sem sessão), então guarda o mínimo: nada de query string, nada do caminho
 * além do primeiro trecho (as URLs públicas levam token) e nenhum dado do corpo além da diretiva e do host bloqueado.
 */
class CspReportController extends Controller
{
    private const TAMANHO_MAXIMO = 10240;

    public function __invoke(Request $request): Response
    {
        if (strlen($request->getContent()) > self::TAMANHO_MAXIMO) {
            return response()->noContent(413);
        }

        $corpo = json_decode($request->getContent(), true);

        // Formatos: "report-uri" ({"csp-report": {...}}) e Reporting API ([{"type": "csp-violation", "body": {...}}]).
        $relato = is_array($corpo) ? ($corpo['csp-report'] ?? $corpo[0]['body'] ?? null) : null;

        if (! is_array($relato)) {
            return response()->noContent();
        }

        $diretiva = Str::limit((string) ($relato['effective-directive'] ?? $relato['effectiveDirective'] ?? $relato['violated-directive'] ?? '?'), 60, '');
        $bloqueado = $this->origem((string) ($relato['blocked-uri'] ?? $relato['blockedURL'] ?? ''));
        $pagina = $this->primeiroTrecho((string) ($relato['document-uri'] ?? $relato['documentURL'] ?? ''));

        if (Cache::add('csp-report:'.md5($diretiva.'|'.$bloqueado.'|'.$pagina), 1, 3600)) {
            Log::warning('CSP: violação relatada', ['diretiva' => $diretiva, 'bloqueado' => $bloqueado, 'pagina' => $pagina]);
        }

        return response()->noContent();
    }

    /**
     * "inline", "eval", "data" e "blob" ficam como estão; URLs viram só esquema + host.
     */
    private function origem(string $uri): string
    {
        if ($uri === '' || ! str_contains($uri, '://')) {
            return Str::limit($uri, 30, '');
        }

        $partes = parse_url($uri);

        return Str::limit(($partes['scheme'] ?? '').'://'.($partes['host'] ?? ''), 80, '');
    }

    private function primeiroTrecho(string $uri): string
    {
        $caminho = (string) parse_url($uri, PHP_URL_PATH);
        $primeiro = explode('/', trim($caminho, '/'))[0] ?? '';

        return '/'.Str::limit($primeiro, 40, '');
    }
}

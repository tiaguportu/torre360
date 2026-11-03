<?php

namespace App\Http\Middleware;

use App\Support\ContentSecurityPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Adiciona cabeçalhos de segurança a toda resposta.
     *
     * Content-Security-Policy em duas camadas (ver {@see ContentSecurityPolicy} e config/seguranca.php): a base
     * (sem plugins, sem `<base>` forjado, sem ser embutido em outro site) vale sempre; a estrita, que restringe
     * as origens de scripts, estilos, imagens e conexões, começa em `report-only` para não quebrar o admin
     * (Filament/Livewire/Alpine/TinyMCE) antes de ser validada nas telas reais.
     * Respostas que já definem a própria CSP (ex.: download isolado de arquivos de usuário) são preservadas.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=(self)');

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $this->aplicarContentSecurityPolicy($response);
        $this->pedirParaNaoIndexar($request, $response);

        return $response;
    }

    /**
     * Páginas abertas por link com token (acordo, proposta, visita, ouvidoria, admissão...) mostram dado de criança ou de dívida. Mesmo que o link
     * vaze, buscador não deve guardá-las: `X-Robots-Tag` vale para qualquer resposta que ele busque. O `robots.txt` não entra de propósito: bloquear
     * a busca impediria o buscador de ver este cabeçalho, e a URL poderia aparecer só com o endereço.
     */
    private function pedirParaNaoIndexar(Request $request, Response $response): void
    {
        $padroes = (array) config('seguranca.noindex', []);

        if ($padroes !== [] && $request->is(...$padroes)) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }
    }

    private function aplicarContentSecurityPolicy(Response $response): void
    {
        $modo = (string) config('seguranca.csp.modo', 'report-only');

        if ($modo === 'off' || $response->headers->has('Content-Security-Policy')) {
            return;
        }

        if ($modo === 'enforce') {
            $response->headers->set('Content-Security-Policy', ContentSecurityPolicy::estrita());

            return;
        }

        $response->headers->set('Content-Security-Policy', ContentSecurityPolicy::base());
        $response->headers->set('Content-Security-Policy-Report-Only', ContentSecurityPolicy::estritaComRelatorio());
    }
}

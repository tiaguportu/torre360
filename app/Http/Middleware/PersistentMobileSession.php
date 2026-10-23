<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PersistentMobileSession
{
    /**
     * Duração máxima da sessão móvel para o portal da família (30 dias em minutos).
     */
    public const DURACAO_MOBILE_MINUTOS = 43200; // 30 dias (substitui o antigo valor inseguro de 525600 = 1 ano)

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $userAgent = (string) $request->userAgent();

        // Detecta se a requisição alega vir do App Mobile
        $isMobileApp = str_contains($userAgent, 'Torre360App') || $request->hasHeader('X-Torre360-App');

        if ($isMobileApp) {
            // Proteção M1: NUNCA estende sessão em rotas do painel administrativo
            if ($request->is('admin*')) {
                return $next($request);
            }

            // Proteção M1: Se o usuário estiver autenticado, proíbe extensão para papéis administrativos privilegiados
            $user = $request->user();
            if ($user && $user->hasAnyRole(['super_admin', 'admin', 'diretoria', 'secretaria', 'coordenador'])) {
                return $next($request);
            }

            // Estende por 30 dias com sliding expiration apenas para perfis de família/aluno
            config(['session.lifetime' => self::DURACAO_MOBILE_MINUTOS]);
            config(['session.expire_on_close' => false]);
        }

        return $next($request);
    }
}

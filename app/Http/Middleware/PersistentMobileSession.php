<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PersistentMobileSession
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Detecta se a requisição vem do App Mobile Torre360 (User-Agent customizado no Capacitor)
        if (str_contains($request->userAgent(), 'Torre360App')) {
            // Aumenta o tempo de vida da sessão para 1 ano (em minutos)
            config(['session.lifetime' => 525600]);
            config(['session.expire_on_close' => false]);
        }

        return $next($request);
    }
}

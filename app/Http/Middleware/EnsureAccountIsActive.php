<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    /**
     * Garante que o usuário autenticado possua conta ativa no sistema.
     * Se a conta estiver desativada ou inativa, revoga a sessão imediatamente.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            auth()->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Sua conta de acesso está inativa ou foi desativada pelo administrador.',
                ], 403);
            }

            abort(403, 'Sua conta de acesso está inativa ou foi desativada pelo administrador.');
        }

        return $next($request);
    }
}

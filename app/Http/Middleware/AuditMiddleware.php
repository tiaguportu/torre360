<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (config('app.audit_enabled', true) && auth()->check()) {
            if ($request->isMethod('GET') && ! $request->ajax()) {
                $user = auth()->user();

                AuditLog::create([
                    'user_id' => $user->id,
                    'event' => 'view',
                    'url' => $request->fullUrl(),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                // Registro automático no Activity Log para usuários com role 'responsavel'
                if ($user->hasRole('responsavel')) {
                    $path = $request->path();
                    $resource = str($path)->after('admin/')->before('/')->title();

                    if ($resource->isNotEmpty() && $resource != 'Admin') {
                        activity()
                            ->withProperties([
                                'url' => $request->fullUrl(),
                                'method' => $request->method(),
                                'ip' => $request->ip(),
                            ])
                            ->log("Responsável visualizou recurso: {$resource} | URL: {$request->fullUrl()}");
                    }
                }
            }
        }

        return $response;
    }
}

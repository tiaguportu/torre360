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

                // Registro automático no Activity Log para usuários com certas roles (responsavel, professor, secretaria)
                $activeRole = session('active_role') ?? $user->active_role;
                $roleToLog = $activeRole;

                if (! $roleToLog) {
                    if ($user->hasRole('super_admin')) {
                        $roleToLog = 'super_admin';
                    } elseif ($user->hasRole('responsavel')) {
                        $roleToLog = 'responsavel';
                    } elseif ($user->hasRole('professor')) {
                        $roleToLog = 'professor';
                    } elseif ($user->hasRole('secretaria')) {
                        $roleToLog = 'secretaria';
                    } else {
                        $roleToLog = $user->roles->first()?->name ?? 'usuario';
                    }
                }

                if ($roleToLog) {
                    $roleLabel = match ($roleToLog) {
                        'responsavel' => 'Responsável',
                        'professor' => 'Professor',
                        'secretaria' => 'Secretaria',
                        'super_admin' => 'Super Admin',
                        'admin' => 'Administrador',
                        default => str($roleToLog)->replace('_', ' ')->title()->toString(),
                    };

                    $path = $request->path();
                    $resource = str($path)->after('admin/')->before('/')->title();

                    if ($resource->isNotEmpty() && $resource != 'Admin') {
                        activity()
                            ->withProperties([
                                'url' => $request->fullUrl(),
                                'method' => $request->method(),
                                'ip' => $request->ip(),
                            ])
                            ->log("{$roleLabel} visualizou recurso: {$resource} | URL: {$request->fullUrl()}");
                    }
                }
            }
        }

        return $response;
    }
}

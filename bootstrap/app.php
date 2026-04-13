<?php

use App\Http\Middleware\PersistentMobileSession;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [
            PersistentMobileSession::class,
        ]);
        $middleware->redirectGuestsTo(fn ($request) => $request->is('api/*') ? null : route('filament.admin.auth.login'));
        $middleware->validateCsrfTokens(except: [
            'api/webhooks/assinafy',
            'mobile/register-token',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

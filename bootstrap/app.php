<?php

use App\Http\Middleware\PersistentMobileSession;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
        // O middleware de autenticação do Filament (que roda antes de qualquer
        // middleware nosso, por prioridade do Laravel) aborta com 403 quando uma
        // conta autenticada não tem acesso ao painel. Para o /portal isso é comum
        // (conta de equipe tentando entrar), então trocamos o 403 seco por um
        // redirecionamento amigável de volta ao painel admin.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 403 || ! $request->is('portal*')) {
                return null;
            }

            $user = auth()->user();

            if (! $user || $user->hasAnyRole(['aluno', 'responsavel'])) {
                return null;
            }

            Notification::make()
                ->title('Portal da Família')
                ->body('Sua conta é de equipe e usa o painel administrativo — o Portal é exclusivo para aluno/responsável.')
                ->warning()
                ->send();

            return redirect()->to(Filament::getPanel('admin')->getUrl());
        });
    })->create();

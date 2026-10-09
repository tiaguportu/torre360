<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LogAuthenticationActivity
{
    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        $logName = 'auth';
        $ip = request()->ip();
        $userAgent = request()->userAgent();

        if ($event instanceof Login) {
            $user = $event->user;
            if (! $user) {
                return;
            }

            activity($logName)
                ->performedOn($user)
                ->causedBy($user)
                ->withProperties([
                    'ip' => $ip,
                    'user_agent' => $userAgent,
                    'guard' => $event->guard ?? 'web',
                ])
                ->log("O usuário {$user->name} realizou login.");
        } elseif ($event instanceof Logout) {
            $user = $event->user;
            if (! $user) {
                return;
            }

            activity($logName)
                ->performedOn($user)
                ->causedBy($user)
                ->withProperties([
                    'ip' => $ip,
                    'user_agent' => $userAgent,
                    'guard' => $event->guard ?? 'web',
                ])
                ->log("O usuário {$user->name} realizou logout.");
        } elseif ($event instanceof Failed) {
            $user = $event->user;
            $email = $event->credentials['email']
                ?? request()->input('email')
                ?? request()->input('data.email')
                ?? 'não informado';

            $activity = activity($logName);

            if ($user) {
                $activity->performedOn($user);
                $description = "Tentativa de login falhou para o usuário cadastrado: {$user->name} (E-mail: {$email}).";
            } else {
                $description = "Tentativa de login falhou com e-mail não localizado: {$email}.";
            }

            $activity
                ->withProperties([
                    'ip' => $ip,
                    'user_agent' => $userAgent,
                    'email_tentado' => $email,
                    'guard' => $event->guard ?? 'web',
                ])
                ->log($description);
        } elseif ($event instanceof PasswordReset) {
            $user = $event->user;
            if (! $user) {
                return;
            }

            // Ao redefinir a senha via link externo de recuperação, revoga todas as sessões anteriores
            if (config('session.driver') === 'database' && Schema::hasTable(config('session.table', 'sessions'))) {
                DB::table(config('session.table', 'sessions'))
                    ->where('user_id', $user->getAuthIdentifier())
                    ->delete();
            }

            activity($logName)
                ->performedOn($user)
                ->causedBy($user)
                ->withProperties([
                    'ip' => $ip,
                    'user_agent' => $userAgent,
                    'acao' => 'senha_redefinida_sessoes_revogadas',
                ])
                ->log("A senha do usuário {$user->name} foi redefinida com sucesso. Todas as sessões anteriores foram revogadas.");
        }
    }
}

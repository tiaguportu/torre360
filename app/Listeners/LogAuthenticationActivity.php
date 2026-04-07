<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class LogAuthenticationActivity
{
    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        $user = $event->user;

        if (! $user) {
            return;
        }

        $description = '';
        $logName = 'auth';

        if ($event instanceof Login) {
            $description = "O usuário {$user->name} realizou login.";
        } elseif ($event instanceof Logout) {
            $description = "O usuário {$user->name} realizou logout.";
        }

        if ($description) {
            activity($logName)
                ->performedOn($user)
                ->causedBy($user)
                ->withProperties([
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ])
                ->log($description);
        }
    }
}

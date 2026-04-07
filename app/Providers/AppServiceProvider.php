<?php

namespace App\Providers;

use App\Listeners\LogAuthenticationActivity;
use App\Listeners\LogSentMessage;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Verified;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(fn ($user, $ability) => $user->hasRole('super_admin') ? true : null);

        Event::listen(
            Verified::class,
            fn ($event) => $event->user->update(['is_active' => true])
        );

        Event::listen(
            MessageSending::class,
            LogSentMessage::class
        );

        Event::listen(
            Login::class,
            LogAuthenticationActivity::class
        );

        Event::listen(
            Logout::class,
            LogAuthenticationActivity::class
        );
    }
}

<?php

namespace App\Providers;

use App\Listeners\LogAuthenticationActivity;
use App\Listeners\LogSentMessage;
use App\Models\LeadScoreConfiguracao;
use App\Models\RiscoEvasaoConfiguracao;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Filament\Tables\Table;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        // Modo estrito de lazy loading (N+1 vira exceção): ligado nos testes ou, em outros ambientes, só por opt-in
        // explícito (DB_PREVENT_LAZY_LOADING=true). Não depende de APP_ENV=production: o servidor pode rodar com
        // outro APP_ENV e um N+1 não pode derrubar a página dos usuários.
        Model::preventLazyLoading(app()->environment('testing') || (bool) config('database.prevent_lazy_loading'));

        // Parâmetros do Lead Score editados no painel sobrescrevem config/lead_score.php.
        LeadScoreConfiguracao::aplicar();

        // Parâmetros do Risco de Evasão editados no painel sobrescrevem config/risco_evasao.php.
        RiscoEvasaoConfiguracao::aplicar();

        Table::configureUsing(function (Table $table): void {
            $table
                ->recordUrl(null)
                ->recordAction(null);
        });

        Gate::before(function ($user, $ability) {
            // Permissões de widgets do Shield devem respeitar a seleção na Role, mesmo para super_admin
            if (is_string($ability) && static::isWidgetShieldPermission($ability)) {
                return null;
            }

            $activeRole = session('active_role');

            if ($activeRole) {
                return $activeRole === 'super_admin' ? true : null;
            }

            return $user->hasRole('super_admin') ? true : null;
        });

        // Permite visualizar arquivos servidos pelo Laravel APENAS se estiver autenticado
        Gate::define('viewApi', fn ($user) => $user !== null);

        Event::listen(
            Verified::class,
            fn ($event) => $event->user->update(['activated_at' => now()])
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

        Queue::after(function (JobProcessed $event) {
            Cache::put('queue_last_run_at', now()->toDateTimeString(), now()->addHours(24));
        });

        // Política de senha: tamanho + complexidade sempre; verificação de vazamento
        // (HaveIBeenPwned) só em produção, para não depender de rede em dev/testes.
        Password::defaults(function () {
            $rule = Password::min(10)->mixedCase()->numbers()->symbols();

            return app()->environment('production') ? $rule->uncompromised() : $rule;
        });
    }

    /**
     * Verifica se uma dada permissão é referente a um Widget gerenciado pelo Filament Shield.
     */
    public static function isWidgetShieldPermission(string $permission): bool
    {
        static $widgetPermissions = null;

        if ($widgetPermissions === null) {
            try {
                $widgets = FilamentShield::getWidgets() ?? [];
                $widgetPermissions = collect($widgets)
                    ->flatMap(fn ($widget) => array_keys($widget['permissions'] ?? []))
                    ->flip()
                    ->all();
            } catch (\Throwable $e) {
                $widgetPermissions = [];
            }
        }

        return isset($widgetPermissions[$permission]);
    }
}

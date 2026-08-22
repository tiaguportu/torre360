<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\ChangePassword;
use App\Filament\Pages\Auth\CustomLogin;
use App\Filament\Pages\Auth\CustomRequestPasswordReset;
use App\Http\Middleware\AuditMiddleware;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class PortalPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('portal')
            ->path('portal')
            ->brandName('Torre360 — Portal da Família')
            ->login(CustomLogin::class)
            ->passwordReset(CustomRequestPasswordReset::class)
            ->emailVerification()
            ->profile(ChangePassword::class)
            ->brandLogo(fn () => view('filament.logo'))
            ->navigationGroups([
                NavigationGroup::make('Meus Dados'),
            ])
            ->favicon(asset('icon.png'))
            ->colors([
                'primary' => '#243468',
                'secondary' => '#5C94AB',
                'warning' => '#DCA814',
                'gray' => Color::Slate,
            ])
            ->font('Inter')
            ->discoverPages(in: app_path('Filament/Portal/Pages'), for: 'App\Filament\Portal\Pages')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                AuditMiddleware::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}

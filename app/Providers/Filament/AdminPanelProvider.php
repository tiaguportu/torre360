<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Filament\Navigation\NavigationGroup;
use Filament\Support\Icons\Heroicon;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\View\PanelsRenderHook;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('Torre360 Gestão Escolar')
            ->login()
            ->registration()
            ->emailVerification()
            ->brandLogo(fn () => view('filament.logo'))
            ->favicon(asset('icon.png'))

            ->colors([
                'primary' => '#243468', // Dark Blue from TORRE
                'secondary' => '#5C94AB', // Teal from tower body
                'warning' => '#DCA814', // Yellow from tower crown
                'gray' => Color::Slate,
            ])
            ->font('Inter')

            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): string => view('filament.hooks.register-link')->render(),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => '
                    <style>
                        .fi-simple-header-heading, 
                        .fi-simple-header-subheading, 
                        .fi-simple-header-action { 
                            display: none !important; 
                        }
                    </style>
                    <link rel="stylesheet" href="' . asset('css/filament/admin/theme.css') . '">',
            )



            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
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
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->navigationGroups([
                NavigationGroup::make('Acadêmico'),
                NavigationGroup::make('Secretaria'),
                NavigationGroup::make('Financeiro'),
                NavigationGroup::make('Configurações')
                    ->collapsed(),
                NavigationGroup::make('Cadastros'),
            ]);


    }
}

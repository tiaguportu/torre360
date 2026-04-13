<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\ChangePassword;
use App\Filament\Pages\Auth\CustomRequestPasswordReset;
use App\Http\Middleware\AuditMiddleware;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

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
            ->passwordReset(CustomRequestPasswordReset::class)
            ->emailVerification()
            ->profile(ChangePassword::class)
            ->brandLogo(fn () => view('filament.logo'))
            ->favicon(asset('icon.png'))
            ->databaseNotifications()
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
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn (): string => view('filament.hooks.git-pull-button')->render(),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => Blade::render("@vite('resources/js/app.js')"),
            )
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
                    <link rel="stylesheet" href="'.asset('css/filament/admin/theme.css').'">',
            )

            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([

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
                AuditMiddleware::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),

            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->navigationGroups([
                NavigationGroup::make('CRM / Comercial')
                    ->icon('heroicon-o-presentation-chart-line'),
                NavigationGroup::make('Acadêmico')
                    ->icon('heroicon-o-academic-cap'),
                NavigationGroup::make('Avaliações')
                    ->icon('heroicon-o-clipboard-document-check'),
                NavigationGroup::make('Calendário e Horários')
                    ->icon('heroicon-o-calendar-days'),
                NavigationGroup::make('Financeiro')
                    ->icon('heroicon-o-banknotes'),
                NavigationGroup::make('Pessoas')
                    ->icon('heroicon-o-users'),
                NavigationGroup::make('Documentos')
                    ->icon('heroicon-o-document-duplicate'),
                NavigationGroup::make('Operacional')
                    ->icon('heroicon-o-wrench-screwdriver'),
                NavigationGroup::make('Localização e Cadastros')
                    ->icon('heroicon-o-map-pin')
                    ->collapsed(),
                NavigationGroup::make('Sistema e Segurança')
                    ->icon('heroicon-o-shield-check')
                    ->collapsed(),
            ]);

    }
}

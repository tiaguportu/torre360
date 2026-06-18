<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\ChangePassword;
use App\Filament\Pages\Auth\CustomLogin;
use App\Filament\Pages\Auth\CustomRequestPasswordReset;
use App\Filament\Pages\Auth\Register;
use App\Http\Middleware\AuditMiddleware;
use App\Http\Middleware\EnsureActiveRole;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
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
            ->login(CustomLogin::class)
            ->registration(Register::class)
            ->passwordReset(CustomRequestPasswordReset::class)
            ->emailVerification()
            ->profile(ChangePassword::class)
            ->brandLogo(fn () => view('filament.logo'))
            ->navigationGroups([
                NavigationGroup::make('CRM / Comercial'),
                NavigationGroup::make('Secretaria'),
                NavigationGroup::make('Acadêmico'),
                NavigationGroup::make('Avaliações'),
                NavigationGroup::make('Currículo (BNCC)'),
                NavigationGroup::make('Preceptoria'),
                NavigationGroup::make('Calendário e Horários'),
                NavigationGroup::make('Financeiro'),
                NavigationGroup::make('Operacional'),
                NavigationGroup::make('Localização e Cadastros')
                    ->collapsed(),
                NavigationGroup::make('Configurações')
                    ->collapsed(),
                NavigationGroup::make('Sistema e Segurança')
                    ->collapsed(),
                NavigationGroup::make('Filament Shield')
                    ->collapsed(),
            ])
            ->userMenuItems([
                MenuItem::make()
                    ->label(fn () => 'Role Ativo: '.(auth()->user()?->active_role ?? 'Nenhum'))
                    ->icon('heroicon-o-shield-check')
                    ->color('primary')
                    ->visible(fn () => auth()->check()),
                MenuItem::make()
                    ->label(fn () => 'Pessoa: '.(auth()->user()?->pessoa?->nome ?? 'Não vinculada'))
                    ->icon('heroicon-o-user-circle')
                    ->visible(fn () => auth()->check() && auth()->user()?->pessoa !== null),
            ])
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
                fn (): string => view('filament.hooks.assistant-chat')->render(),
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
                    <link rel="stylesheet" href="'.asset('css/filament/admin/theme.css').'">
                    <link rel="manifest" href="/manifest.json">
                    <meta name="apple-mobile-web-app-capable" content="yes">
                    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
                    <meta name="apple-mobile-web-app-title" content="Torre360">
                    <link rel="apple-touch-icon" href="/pwa/apple-touch-icon.png">
                    <meta name="theme-color" content="#243468">
                    <meta property="og:type" content="website">
                    <meta property="og:title" content="Torre360 Gestão Escolar">
                    <meta property="og:description" content="Sistema de Gestão Escolar Torre360.">
                    <meta property="og:image" content="'.asset('logo-login.png').'">
                    <meta property="og:image:secure_url" content="'.asset('logo-login.png').'">
                    <meta property="twitter:card" content="summary_large_image">
                    <meta property="twitter:image" content="'.asset('logo-login.png').'">
                ',
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
                EnsureActiveRole::class,
                DispatchServingFilamentEvent::class,
                AuditMiddleware::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);

        return $panel;
    }
}

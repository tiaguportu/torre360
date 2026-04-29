<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\ChangePassword;
use App\Filament\Pages\Auth\CustomLogin;
use App\Filament\Pages\Auth\CustomRequestPasswordReset;
use App\Filament\Pages\Auth\Register;
use App\Filament\Resources\Preceptorias\PreceptoriaResource;
use App\Http\Middleware\AuditMiddleware;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
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
            ->login(CustomLogin::class)
            ->registration(Register::class)
            ->passwordReset(CustomRequestPasswordReset::class)
            ->emailVerification()
            ->profile(ChangePassword::class)
            ->brandLogo(fn() => view('filament.logo'))
            ->userMenuItems([
                MenuItem::make()
                    ->label(fn() => 'Roles: ' . auth()->user()->roles->pluck('name')->join(', '))
                    ->icon('heroicon-o-shield-check'),
                MenuItem::make()
                    ->label(fn() => 'Pessoa: ' . (auth()->user()->pessoa?->nome ?? 'Não vinculada'))
                    ->icon('heroicon-o-user-circle')
                    ->visible(fn() => auth()->user()->pessoa !== null),
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
                fn(): string => view('filament.hooks.git-pull-button')->render(),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn(): string => Blade::render("@vite('resources/js/app.js')"),
            )
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn(): string => view('filament.hooks.register-link')->render(),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn(): string => '
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
            ->navigation(function (\Filament\Navigation\NavigationBuilder $builder): \Filament\Navigation\NavigationBuilder {
                $user = auth()->user();

                // Lógica para Responsável: Grupos por Aluno
                if ($user?->hasRole('responsavel')) {
                    $pessoa = $user->pessoa;
                    $alunos = $pessoa?->alunos ?? collect();
                    $navGroups = [
                        NavigationGroup::make('Principal')
                            ->items([
                                ...Dashboard::getNavigationItems(),
                            ]),
                    ];

                    foreach ($alunos as $aluno) {
                        $matriculaAtiva = $aluno->matriculas()->where('situacao', 'ativa')->first();
                        if ($matriculaAtiva) {
                            $navGroups[] = NavigationGroup::make("Aluno: {$aluno->nome}")
                                ->items([
                                    NavigationItem::make('Preceptorias')
                                        ->icon('heroicon-o-calendar-days')
                                        ->url(fn() => PreceptoriaResource::getUrl('index', [
                                            'tableFilters[matricula][value]' => $matriculaAtiva->id,
                                        ])),
                                ]);
                        }
                    }

                    return $builder->groups($navGroups);
                }

                // Lógica para demais usuários: Mantém a estrutura de grupos automática
                // O Filament v5 preencherá os itens dos recursos automaticamente dentro desses grupos
                return $builder
                    ->items([
                        ...Dashboard::getNavigationItems(),
                    ])
                    ->groups([
                        NavigationGroup::make('CRM / Comercial'),
                        NavigationGroup::make('Acadêmico'),
                        NavigationGroup::make('Avaliações'),
                        NavigationGroup::make('Calendário e Horários'),
                        NavigationGroup::make('Financeiro'),
                        NavigationGroup::make('Pessoas'),
                        NavigationGroup::make('Documentos'),
                        NavigationGroup::make('Operacional'),
                        NavigationGroup::make('Localização e Cadastros')->collapsed(),
                        NavigationGroup::make('Sistema e Segurança')->collapsed(),
                    ]);
            });

    }
}

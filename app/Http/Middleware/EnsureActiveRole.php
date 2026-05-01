<?php

namespace App\Http\Middleware;

use App\Filament\Resources\Matriculas\MatriculaResource;
use App\Filament\Resources\Preceptorias\PreceptoriaResource;
use Closure;
use Filament\Facades\Filament;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Não processa lógica de navegação em rotas de visualização de documentos ou fora do admin
        if ($request->is('visualizar-documento/*') || ! str($request->path())->startsWith('admin')) {
            return $next($request);
        }

        if (auth()->check()) {
            $user = auth()->user();
            $roles = $user->roles->pluck('name')->toArray();
            $activeRole = session('active_role');

            // Se não houver role na sessão ou o role da sessão não pertencer mais ao usuário
            if (! $activeRole || ! in_array($activeRole, $roles)) {
                if (! empty($roles)) {
                    session(['active_role' => $roles[0]]);
                    $activeRole = $roles[0];
                } else {
                    session()->forget('active_role');
                    $activeRole = null;
                }
            }

            // Registra os itens de menu dinamicamente
            Filament::registerUserMenuItems(
                $user->roles->map(
                    fn ($role) => MenuItem::make()
                        ->label("Atuar como: {$role->name}")
                        ->icon($role->name === $activeRole ? 'heroicon-s-check-circle' : 'heroicon-o-arrow-path')
                        ->url(route('switch-role', ['role' => $role->name]))
                        ->visible(fn () => $role->name !== session('active_role'))
                )->toArray()
            );

            // Sobrescreve a navegação se o role ativo for 'responsavel'
            if (in_array($activeRole, ['responsavel', 'aluno']) && ! $user->hasRole('super_admin')) {
                Filament::getCurrentPanel()->navigation(function (NavigationBuilder $builder) use ($user, $activeRole): NavigationBuilder {
                    $pessoa = $user->pessoa;
                    $alunos = $activeRole === 'responsavel'
                        ? ($pessoa?->alunos ?? collect())
                        : collect([$pessoa])->filter();

                    $groups = [
                        NavigationGroup::make('Principal')
                            ->items([
                                ...Dashboard::getNavigationItems(),
                            ]),
                        NavigationGroup::make('Preceptoria')
                            ->items([
                                NavigationItem::make('Agendar preceptoria')
                                    ->icon('heroicon-o-calendar-days')
                                    ->url(fn () => PreceptoriaResource::getUrl('agendar')),
                            ]),
                    ];

                    foreach ($alunos as $aluno) {
                        $matriculaAtiva = $aluno->matriculas()->where('situacao', 'ativa')->first();

                        if ($matriculaAtiva) {
                            $avatarUrl = $aluno->foto
                                ? Storage::disk('local')->temporaryUrl($aluno->foto, now()->addHours(2))
                                : 'https://ui-avatars.com/api/?name='.urlencode($aluno->nome).'&color=7F9CF5&background=EBF4FF';

                            $groups[] = NavigationGroup::make($aluno->nome)
                                ->icon(new HtmlString('
                                    <div style="width: 2.25rem; height: 2.25rem; border-radius: 50%; overflow: hidden; display: flex; align-items: center; justify-content: center; flex-shrink: 0;" class="shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
                                        <img src="'.$avatarUrl.'" style="width: 100%; height: 100%; object-fit: cover;">
                                    </div>
                                '))
                                ->items([
                                    NavigationItem::make('Boletim Escolar')
                                        ->url(fn () => MatriculaResource::getUrl('boletim', ['record' => $matriculaAtiva->id]))
                                        ->visible(fn () => $matriculaAtiva->notas()->exists()),

                                    NavigationItem::make('Preceptorias')
                                        ->url(fn () => PreceptoriaResource::getUrl('index', [
                                            'tableFilters[matricula][value]' => $matriculaAtiva->id,
                                        ])),

                                    NavigationItem::make('Documentos')
                                        ->url(fn () => MatriculaResource::getUrl('documentos', ['record' => $matriculaAtiva->id]))
                                        ->badge(fn () => $matriculaAtiva->getMissingMandatoryDocumentsCount() ?: null, color: 'danger'),
                                ]);
                        }
                    }

                    return $builder->groups($groups);
                });
            }
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Filament\Navigation\MenuItem;
use Illuminate\Http\Request;
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
                $user->roles->map(fn ($role) => MenuItem::make()
                    ->label("Atuar como: {$role->name}")
                    ->icon($role->name === $activeRole ? 'heroicon-s-check-circle' : 'heroicon-o-arrow-path')
                    ->url(route('switch-role', ['role' => $role->name]))
                    ->visible(fn () => $role->name !== session('active_role'))
                )->toArray()
            );
        }

        return $next($request);
    }
}

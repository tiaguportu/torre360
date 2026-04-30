<?php

namespace App\Http\Controllers;

use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;

class RoleController extends Controller
{
    public function switch(string $role): RedirectResponse
    {
        $user = auth()->user();

        if ($user && $user->hasRole($role)) {
            session(['active_role' => $role]);

            Notification::make()
                ->title('Contexto alterado')
                ->body("Você agora está operando como: **{$role}**")
                ->success()
                ->send();
        }

        return redirect()->intended(config('filament.path', 'admin'));
    }
}

<?php

namespace App\Traits;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Role;

trait HasCustomWidgetShield
{
    use HasWidgetShield;

    /**
     * Retorna o nome da permissão no Filament Shield para este widget.
     */
    public static function getWidgetPermissionName(): string
    {
        return static::getWidgetPermission() ?? ('View:'.class_basename(static::class));
    }

    /**
     * Verifica se o papel ativo atual possui a permissão explícita no Shield para visualizar este widget.
     * Mesmo para o papel super_admin, a permissão deve estar explicitamente marcada na Role no Shield.
     */
    public static function hasWidgetShieldPermission(): bool
    {
        $user = Filament::auth()?->user() ?? auth()->user();

        if (! $user) {
            return false;
        }

        $activeRole = session('active_role') ?? $user->active_role;

        if (! $activeRole) {
            return false;
        }

        $permission = static::getWidgetPermissionName();

        try {
            /** @var Role|null $role */
            $role = Role::findByName($activeRole, 'web');

            return $role ? $role->hasPermissionTo($permission) : false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Determina se o widget pode ser exibido na tela inicial.
     */
    public static function canView(): bool
    {
        return static::hasWidgetShieldPermission();
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Página "Roteiro de qualificação" (CRM / Comercial): só super admin e administração a configuram por padrão. Quem tem um
 * papel criado à mão recebe a permissão em Perfis de acesso.
 */
return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::firstOrCreate(['name' => 'View:ConfiguracaoRoteiroQualificacao', 'guard_name' => 'web']);

        foreach (Role::whereIn('name', ['super_admin', 'admin'])->get() as $role) {
            if (! $role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        //
    }
};

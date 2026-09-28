<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $entities = [
            'EventoEscolar',
            'EventoConfirmacao',
            'AtendimentoSetor',
            'AtendimentoChamado',
        ];

        $actions = [
            'ViewAny',
            'View',
            'Create',
            'Update',
            'Delete',
            'DeleteAny',
        ];

        $permissions = [];
        foreach ($entities as $entity) {
            foreach ($actions as $action) {
                $permissions[] = "{$action}:{$entity}";
            }
        }

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(
                ['name' => $permissionName, 'guard_name' => 'web'],
                ['name' => $permissionName, 'guard_name' => 'web']
            );
        }

        // Concede permissões aos papéis super_admin, admin e secretaria
        $rolesToSync = Role::whereIn('name', ['super_admin', 'admin', 'secretaria', 'coordenador'])->get();

        foreach ($rolesToSync as $role) {
            foreach ($permissions as $permissionName) {
                $permission = Permission::where('name', $permissionName)->first();
                if ($permission && ! $role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $entities = [
            'EventoEscolar',
            'EventoConfirmacao',
            'AtendimentoSetor',
            'AtendimentoChamado',
        ];

        $actions = [
            'ViewAny',
            'View',
            'Create',
            'Update',
            'Delete',
            'DeleteAny',
        ];

        foreach ($entities as $entity) {
            foreach ($actions as $action) {
                $permissionName = "{$action}:{$entity}";
                Permission::where('name', $permissionName)->delete();
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};

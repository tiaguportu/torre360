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

        $permissions = [
            'View:Ensalamento',
            'Manage:Ensalamento',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(
                ['name' => $permissionName, 'guard_name' => 'web'],
                ['name' => $permissionName, 'guard_name' => 'web']
            );
        }

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
        $permissions = [
            'View:Ensalamento',
            'Manage:Ensalamento',
        ];

        foreach ($permissions as $permissionName) {
            Permission::where('name', $permissionName)->delete();
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};

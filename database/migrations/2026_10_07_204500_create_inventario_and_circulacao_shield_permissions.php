<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $crudActions = [
        'ViewAny',
        'View',
        'Create',
        'Update',
        'Delete',
        'DeleteAny',
    ];

    private array $models = [
        'InventarioAcervo',
    ];

    public function up(): void
    {
        $permIdsByName = [];

        foreach ($this->models as $model) {
            foreach ($this->crudActions as $action) {
                $name = "{$action}:{$model}";
                $perm = Permission::firstOrCreate([
                    'name' => $name,
                    'guard_name' => 'web',
                ]);
                $permIdsByName[$name] = $perm->id;
            }
        }

        $superAdmin = Role::where('name', 'super_admin')->first();
        if ($superAdmin) {
            foreach ($permIdsByName as $permId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permId,
                    'role_id' => $superAdmin->id,
                ]);
            }
        }

        $admin = Role::where('name', 'admin')->first();
        if ($admin) {
            foreach ($permIdsByName as $permId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permId,
                    'role_id' => $admin->id,
                ]);
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach ($this->models as $model) {
            foreach ($this->crudActions as $action) {
                Permission::where('name', "{$action}:{$model}")->delete();
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};

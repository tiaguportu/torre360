<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $legacyPerms = Permission::where('name', 'like', '%::%')->get();

        if ($legacyPerms->isNotEmpty()) {
            $ids = $legacyPerms->pluck('id')->all();

            DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
            Permission::whereIn('id', $ids)->delete();

            app()[PermissionRegistrar::class]->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        // Operação de higienização de chaves obsoletas e duplicadas; não requer reversão.
    }
};

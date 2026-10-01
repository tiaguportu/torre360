<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $papeis = ['super_admin', 'admin', 'financeiro'];

    private array $acoes = ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->acoes as $acao) {
            $permissao = Permission::firstOrCreate(['name' => "{$acao}:ContaPagar", 'guard_name' => 'web']);

            foreach (Role::whereIn('name', $this->papeis)->get() as $role) {
                if (! $role->hasPermissionTo($permissao)) {
                    $role->givePermissionTo($permissao);
                }
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach ($this->acoes as $acao) {
            Permission::where('name', "{$acao}:ContaPagar")->delete();
        }
    }
};

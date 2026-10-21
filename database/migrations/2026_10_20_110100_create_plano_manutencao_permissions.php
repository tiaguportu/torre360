<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * @var array<string, list<string>>
     */
    private array $permissoes = [
        'ViewAny:PlanoManutencao' => ['super_admin', 'admin', 'secretaria', 'coordenador'],
        'View:PlanoManutencao' => ['super_admin', 'admin', 'secretaria', 'coordenador'],
        'Create:PlanoManutencao' => ['super_admin', 'admin', 'secretaria'],
        'Update:PlanoManutencao' => ['super_admin', 'admin', 'secretaria'],
        'Delete:PlanoManutencao' => ['super_admin', 'admin'],
        'DeleteAny:PlanoManutencao' => ['super_admin', 'admin'],
        'GerarOrdem:PlanoManutencao' => ['super_admin', 'admin', 'secretaria'],
        'RegistrarExecucao:PlanoManutencao' => ['super_admin', 'admin', 'secretaria'],
    ];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->permissoes as $nome => $papeis) {
            $permissao = Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']);

            foreach (Role::whereIn('name', $papeis)->get() as $papel) {
                if (! $papel->hasPermissionTo($permissao)) {
                    $papel->givePermissionTo($permissao);
                }
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::whereIn('name', array_keys($this->permissoes))->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};

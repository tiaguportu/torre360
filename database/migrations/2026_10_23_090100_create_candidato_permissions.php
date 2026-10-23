<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Permissões do módulo Banco de Talentos / Candidatos (RH).
     *
     * @var array<string, list<string>>
     */
    private array $permissoes = [
        'ViewAny:Candidato' => ['super_admin', 'admin'],
        'View:Candidato' => ['super_admin', 'admin'],
        'Create:Candidato' => ['super_admin', 'admin'],
        'Update:Candidato' => ['super_admin', 'admin'],
        'Delete:Candidato' => ['super_admin', 'admin'],
        'DeleteAny:Candidato' => ['super_admin', 'admin'],
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

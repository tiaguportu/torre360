<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Admissão e desligamento mexem em documentos pessoais, exames e verbas do empregado: só a administração, como o
     * resto do RH. Concluir um desligamento tira os acessos da pessoa ao sistema, por isso tem permissão própria.
     *
     * @var array<string, list<string>>
     */
    private array $permissoes = [
        'ViewAny:ProcessoFuncionario' => ['super_admin', 'admin'],
        'View:ProcessoFuncionario' => ['super_admin', 'admin'],
        'Create:ProcessoFuncionario' => ['super_admin', 'admin'],
        'Update:ProcessoFuncionario' => ['super_admin', 'admin'],
        'Concluir:ProcessoFuncionario' => ['super_admin', 'admin'],
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

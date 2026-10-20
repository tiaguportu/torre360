<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Quem enxerga e pede reservas: qualquer papel que precise de um espaço. Aprovar (`Confirmar`) fica com a
     * gestão, e excluir de vez, com as funções administrativas.
     *
     * @var array<string, list<string>>
     */
    private array $permissoes = [
        'ViewAny:ReservaEspaco' => ['super_admin', 'admin', 'secretaria', 'coordenador', 'professor'],
        'View:ReservaEspaco' => ['super_admin', 'admin', 'secretaria', 'coordenador', 'professor'],
        'Create:ReservaEspaco' => ['super_admin', 'admin', 'secretaria', 'coordenador', 'professor'],
        'Update:ReservaEspaco' => ['super_admin', 'admin', 'secretaria', 'coordenador', 'professor'],
        'Confirmar:ReservaEspaco' => ['super_admin', 'admin', 'secretaria', 'coordenador'],
        'Delete:ReservaEspaco' => ['super_admin', 'admin'],
        'DeleteAny:ReservaEspaco' => ['super_admin', 'admin'],
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

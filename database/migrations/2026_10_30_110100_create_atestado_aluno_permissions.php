<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * O atestado traz dado de saúde: a secretaria registra, corrige e cancela; a coordenação só consulta. Professor e
     * família não abrem o comprovante (a família já tem o original). Sem exclusão: o histórico não se reescreve.
     *
     * @var array<string, list<string>>
     */
    private array $permissoes = [
        'ViewAny:AtestadoAluno' => ['super_admin', 'admin', 'secretaria', 'coordenador'],
        'View:AtestadoAluno' => ['super_admin', 'admin', 'secretaria', 'coordenador'],
        'Create:AtestadoAluno' => ['super_admin', 'admin', 'secretaria'],
        'Update:AtestadoAluno' => ['super_admin', 'admin', 'secretaria'],
        'Cancelar:AtestadoAluno' => ['super_admin', 'admin', 'secretaria'],
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

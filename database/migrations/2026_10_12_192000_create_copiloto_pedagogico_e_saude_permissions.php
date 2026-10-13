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
        // Copiloto Pedagógico
        'GerarComCopiloto:PlanoAula' => ['super_admin', 'admin', 'coordenador', 'professor'],
        'GerarPei:PlanoAula' => ['super_admin', 'admin', 'coordenador'],
        'ExportarPei:PlanoAula' => ['super_admin', 'admin', 'coordenador'],

        // LGPD - Saúde e Acessibilidade (B.0)
        'ViewAny:NecessidadeEducacaoEspecial' => ['super_admin'],
        'View:NecessidadeEducacaoEspecial' => ['super_admin'],
        'Create:NecessidadeEducacaoEspecial' => ['super_admin'],
        'Update:NecessidadeEducacaoEspecial' => ['super_admin'],
        'Delete:NecessidadeEducacaoEspecial' => ['super_admin'],

        'ViewAny:TranstornoAprendizagem' => ['super_admin'],
        'View:TranstornoAprendizagem' => ['super_admin'],
        'Create:TranstornoAprendizagem' => ['super_admin'],
        'Update:TranstornoAprendizagem' => ['super_admin'],
        'Delete:TranstornoAprendizagem' => ['super_admin'],

        'ViewAny:RecursoAcessibilidade' => ['super_admin'],
        'View:RecursoAcessibilidade' => ['super_admin'],
        'Create:RecursoAcessibilidade' => ['super_admin'],
        'Update:RecursoAcessibilidade' => ['super_admin'],
        'Delete:RecursoAcessibilidade' => ['super_admin'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->permissoes as $nome => $papeis) {
            $permissao = Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']);

            foreach (Role::whereIn('name', $papeis)->get() as $role) {
                if (! $role->hasPermissionTo($permissao)) {
                    $role->givePermissionTo($permissao);
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
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::whereIn('name', array_keys($this->permissoes))
            ->where('guard_name', 'web')
            ->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};

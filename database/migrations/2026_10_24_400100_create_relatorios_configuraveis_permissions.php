<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Cada pessoa monta e agenda os seus relatórios; "VerTodos" deixa a administração enxergar e reaproveitar os de todos. Os
     * dados de cada relatório seguem a permissão da própria fonte (a mesma da tela correspondente), conferida a cada execução.
     *
     * @var array<string, list<string>>
     */
    private array $permissoes = [
        'ViewAny:RelatorioSalvo' => ['super_admin', 'admin', 'secretaria', 'coordenador'],
        'View:RelatorioSalvo' => ['super_admin', 'admin', 'secretaria', 'coordenador'],
        'Create:RelatorioSalvo' => ['super_admin', 'admin', 'secretaria', 'coordenador'],
        'Update:RelatorioSalvo' => ['super_admin', 'admin', 'secretaria', 'coordenador'],
        'Delete:RelatorioSalvo' => ['super_admin', 'admin', 'secretaria', 'coordenador'],
        'Executar:RelatorioSalvo' => ['super_admin', 'admin', 'secretaria', 'coordenador'],
        'VerTodos:RelatorioSalvo' => ['super_admin', 'admin'],
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

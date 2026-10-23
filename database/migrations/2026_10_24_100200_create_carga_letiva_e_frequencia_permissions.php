<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * A coordenação e a administração apuram, abrem os casos e registram o contato com a família; a secretaria consulta e
     * registra o envio do ofício. `Reposicao:Turma` marca a aula que não aconteceu e agenda a reposição.
     *
     * @var array<string, list<string>>
     */
    private array $permissoes = [
        'CargaLetiva:Turma' => ['super_admin', 'admin', 'secretaria', 'coordenador'],
        'Reposicao:Turma' => ['super_admin', 'admin', 'coordenador'],
        'ViewAny:CasoFrequencia' => ['super_admin', 'admin', 'secretaria', 'coordenador'],
        'View:CasoFrequencia' => ['super_admin', 'admin', 'secretaria', 'coordenador'],
        'Create:CasoFrequencia' => ['super_admin', 'admin', 'coordenador'],
        'Update:CasoFrequencia' => ['super_admin', 'admin', 'coordenador'],
        'Enviar:CasoFrequencia' => ['super_admin', 'admin', 'secretaria', 'coordenador'],
        'Apurar:CasoFrequencia' => ['super_admin', 'admin', 'coordenador'],
        'ViewAny:ConselhoTutelar' => ['super_admin', 'admin', 'secretaria', 'coordenador'],
        'View:ConselhoTutelar' => ['super_admin', 'admin', 'secretaria', 'coordenador'],
        'Create:ConselhoTutelar' => ['super_admin', 'admin', 'secretaria'],
        'Update:ConselhoTutelar' => ['super_admin', 'admin', 'secretaria'],
        'Delete:ConselhoTutelar' => ['super_admin', 'admin'],
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

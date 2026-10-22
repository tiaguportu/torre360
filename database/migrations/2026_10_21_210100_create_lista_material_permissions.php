<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * A secretaria monta e publica a lista; a coordenação pode consultar. As famílias veem a lista publicada no Portal,
     * sem permissão própria (o acesso vem do vínculo com o aluno).
     *
     * @var array<string, list<string>>
     */
    private array $permissoes = [
        'ViewAny:ListaMaterialEscolar' => ['super_admin', 'admin', 'secretaria', 'coordenador'],
        'View:ListaMaterialEscolar' => ['super_admin', 'admin', 'secretaria', 'coordenador'],
        'Create:ListaMaterialEscolar' => ['super_admin', 'admin', 'secretaria'],
        'Update:ListaMaterialEscolar' => ['super_admin', 'admin', 'secretaria'],
        'Delete:ListaMaterialEscolar' => ['super_admin', 'admin', 'secretaria'],
        'Publicar:ListaMaterialEscolar' => ['super_admin', 'admin', 'secretaria'],
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

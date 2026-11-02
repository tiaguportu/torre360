<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Onda 86: quem cuida da agenda de visitas do CRM. Papéis que a escola não criou são ignorados; um papel feito à mão
     * (consultor, comercial) recebe as permissões em Perfis de acesso.
     *
     * @var array<string, list<string>>
     */
    private array $permissoes = [
        'ViewAny:DisponibilidadeVisita' => ['super_admin', 'admin', 'secretaria', 'coordenador'],
        'View:DisponibilidadeVisita' => ['super_admin', 'admin', 'secretaria', 'coordenador'],
        'Create:DisponibilidadeVisita' => ['super_admin', 'admin', 'secretaria', 'coordenador'],
        'Update:DisponibilidadeVisita' => ['super_admin', 'admin', 'secretaria', 'coordenador'],
        'Delete:DisponibilidadeVisita' => ['super_admin', 'admin', 'secretaria', 'coordenador'],
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

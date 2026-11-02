<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * P2 (Ondas 83, 80, 34, 35 e 63). Aplicar a alteração pedida num requerimento é da secretaria e da administração; ler os
     * pedidos restritos do titular da LGPD e a ouvidoria é só da administração (quem a escola quiser entra pelo comitê e por
     * Perfis de acesso). Papéis que a escola não criou são ignorados.
     *
     * @var array<string, list<string>>
     */
    private array $permissoes = [
        // Central de Atendimento e requerimentos
        'Aplicar:AtendimentoChamado' => ['super_admin', 'admin', 'secretaria'],
        'VerRestritos:AtendimentoChamado' => ['super_admin', 'admin'],

        // Ouvidoria e convivência escolar
        'ViewAny:Denuncia' => ['super_admin', 'admin'],
        'View:Denuncia' => ['super_admin', 'admin'],
        'Tratar:Denuncia' => ['super_admin', 'admin'],
        'VerTodas:Denuncia' => ['super_admin', 'admin'],
        'Gerenciar:DenunciaComite' => ['super_admin', 'admin'],
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

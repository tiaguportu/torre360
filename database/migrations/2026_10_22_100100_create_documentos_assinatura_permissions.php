<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * A secretaria e a administração criam, enviam e cancelam; a coordenação consulta. Os documentos de RH (contrato de
     * trabalho, termos do funcionário) só aparecem para quem também pode ver Admissão e Desligamento.
     *
     * @var array<string, list<string>>
     */
    private array $permissoes = [
        'ViewAny:DocumentoAssinatura' => ['super_admin', 'admin', 'secretaria', 'coordenador'],
        'View:DocumentoAssinatura' => ['super_admin', 'admin', 'secretaria', 'coordenador'],
        'Create:DocumentoAssinatura' => ['super_admin', 'admin', 'secretaria'],
        'Enviar:DocumentoAssinatura' => ['super_admin', 'admin', 'secretaria'],
        'Cancelar:DocumentoAssinatura' => ['super_admin', 'admin', 'secretaria'],
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

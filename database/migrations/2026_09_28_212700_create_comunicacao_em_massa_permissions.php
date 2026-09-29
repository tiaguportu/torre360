<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $papeis = ['super_admin', 'admin', 'secretaria'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissoes = ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny', 'Enviar'];
        $nomes = array_map(fn (string $acao) => "{$acao}:ComunicacaoEmMassa", $permissoes);

        foreach ($nomes as $nome) {
            $permissao = Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']);

            foreach (Role::whereIn('name', $this->papeis)->get() as $role) {
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

        $permissoes = ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny', 'Enviar'];
        Permission::whereIn('name', array_map(fn (string $acao) => "{$acao}:ComunicacaoEmMassa", $permissoes))
            ->where('guard_name', 'web')
            ->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};

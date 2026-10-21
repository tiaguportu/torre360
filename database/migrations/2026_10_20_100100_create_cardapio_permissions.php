<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Ver e montar o cardápio é rotina da secretaria. Já `VerAlergicos` expõe, por refeição, quais alunos têm
     * restrição alimentar (dado de saúde de criança, da ficha médica): segue o precedente de `FichaMedica`
     * (`docs/lgpd_saude_policies.md`) e nasce só com a administração. Quem prepara as refeições precisa receber
     * essa permissão em Shield → Roles.
     *
     * @var array<string, list<string>>
     */
    private array $permissoes = [
        'ViewAny:Cardapio' => ['super_admin', 'admin', 'secretaria', 'coordenador', 'professor'],
        'View:Cardapio' => ['super_admin', 'admin', 'secretaria', 'coordenador', 'professor'],
        'Create:Cardapio' => ['super_admin', 'admin', 'secretaria'],
        'Update:Cardapio' => ['super_admin', 'admin', 'secretaria'],
        'Publicar:Cardapio' => ['super_admin', 'admin', 'secretaria'],
        'Delete:Cardapio' => ['super_admin', 'admin', 'secretaria'],
        'DeleteAny:Cardapio' => ['super_admin', 'admin', 'secretaria'],
        'VerAlergicos:Cardapio' => ['super_admin', 'admin'],
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

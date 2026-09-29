<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Papéis que operam cada entidade/widget do CRM de captação.
     * Leads da landing são B2B (escolas interessadas no sistema) e ficam restritos à administração.
     *
     * @var array<string, list<string>>
     */
    private array $entidades = [
        'CampanhaMarketing' => ['super_admin', 'admin', 'secretaria'],
        'VisitaInteressado' => ['super_admin', 'admin', 'secretaria'],
        'LandingLead' => ['super_admin', 'admin'],
    ];

    /**
     * @var array<string, list<string>>
     */
    private array $widgets = [
        'View:ConversaoCampanhaWidget' => ['super_admin', 'admin', 'secretaria'],
        'View:ConversaoOrigemWidget' => ['super_admin', 'admin', 'secretaria'],
    ];

    private array $acoes = ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $concessoes = [];

        foreach ($this->entidades as $entidade => $papeis) {
            foreach ($this->acoes as $acao) {
                $concessoes["{$acao}:{$entidade}"] = $papeis;
            }
        }

        foreach ($this->widgets as $permissao => $papeis) {
            $concessoes[$permissao] = $papeis;
        }

        foreach ($concessoes as $nome => $papeis) {
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

        $nomes = array_keys($this->widgets);

        foreach (array_keys($this->entidades) as $entidade) {
            foreach ($this->acoes as $acao) {
                $nomes[] = "{$acao}:{$entidade}";
            }
        }

        Permission::whereIn('name', $nomes)->where('guard_name', 'web')->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};

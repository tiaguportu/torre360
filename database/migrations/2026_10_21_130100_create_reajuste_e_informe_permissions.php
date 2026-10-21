<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Reajuste muda a receita da escola inteira: só a administração. O informe anual traz CPF e valores pagos de
     * cada família: administração e secretaria.
     *
     * @var array<string, list<string>>
     */
    private array $permissoes = [
        'ViewAny:CenarioReajuste' => ['super_admin', 'admin'],
        'View:CenarioReajuste' => ['super_admin', 'admin'],
        'Create:CenarioReajuste' => ['super_admin', 'admin'],
        'Update:CenarioReajuste' => ['super_admin', 'admin'],
        'Delete:CenarioReajuste' => ['super_admin', 'admin'],
        'Aprovar:CenarioReajuste' => ['super_admin', 'admin'],
        'Aplicar:CenarioReajuste' => ['super_admin', 'admin'],
        'Comunicar:CenarioReajuste' => ['super_admin', 'admin'],

        'GerarInformeAnual:Fatura' => ['super_admin', 'admin', 'secretaria'],
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

<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * @var array<string, list<string>>
     */
    private array $entidades = [
        'MatrizCurricular' => ['super_admin', 'admin', 'coordenador'],
        'Sala' => ['super_admin', 'admin', 'secretaria', 'coordenador'],
        'GradeHorario' => ['super_admin', 'admin', 'coordenador'],
        'PlanoAula' => ['super_admin', 'admin', 'coordenador', 'professor'],
    ];

    /**
     * @var array<string, list<string>>
     */
    private array $acoesCustomizadas = [
        'GerarCronograma:Turma' => ['super_admin', 'admin', 'coordenador'],
    ];

    private array $acoes = ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $concessoes = $this->acoesCustomizadas;

        foreach ($this->entidades as $entidade => $papeis) {
            foreach ($this->acoes as $acao) {
                $concessoes["{$acao}:{$entidade}"] = $papeis;
            }
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

        $nomes = array_keys($this->acoesCustomizadas);

        foreach (array_keys($this->entidades) as $entidade) {
            foreach ($this->acoes as $acao) {
                $nomes[] = "{$acao}:{$entidade}";
            }
        }

        Permission::whereIn('name', $nomes)->where('guard_name', 'web')->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};

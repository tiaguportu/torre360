<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Resultado da escola (Ondas 45, 76, 77 e 78). O financeiro vê e cuida das contas recorrentes, do fluxo projetado e do resultado por
     * turma; o Painel da Direção e as metas são da administração (cada indicador do painel ainda exige a permissão da tela de onde o
     * número vem); o simulador do próximo ano é de quem planeja turmas. Papéis que a escola não criou são ignorados.
     *
     * @var array<string, list<string>>
     */
    private array $permissoes = [
        // Contas recorrentes (Onda 78)
        'ViewAny:ContaRecorrente' => ['super_admin', 'admin', 'financeiro'],
        'View:ContaRecorrente' => ['super_admin', 'admin', 'financeiro'],
        'Create:ContaRecorrente' => ['super_admin', 'admin', 'financeiro'],
        'Update:ContaRecorrente' => ['super_admin', 'admin', 'financeiro'],
        'Delete:ContaRecorrente' => ['super_admin', 'admin', 'financeiro'],
        'View:FluxoProjetado' => ['super_admin', 'admin', 'financeiro'],

        // Resultado realizado por turma (Onda 77): dado sensível para a gestão
        'View:RentabilidadeRealizada' => ['super_admin', 'admin', 'financeiro'],

        // Painel da Direção e metas (Onda 45)
        'View:PainelDirecao' => ['super_admin', 'admin', 'financeiro', 'coordenador'],
        'Fotografar:PainelDirecao' => ['super_admin', 'admin'],
        'ViewAny:IndicadorMeta' => ['super_admin', 'admin', 'financeiro'],
        'View:IndicadorMeta' => ['super_admin', 'admin', 'financeiro'],
        'Create:IndicadorMeta' => ['super_admin', 'admin'],
        'Update:IndicadorMeta' => ['super_admin', 'admin'],
        'Delete:IndicadorMeta' => ['super_admin', 'admin'],

        // Simulador do próximo ano letivo (Onda 76)
        'View:SimuladorProximoAno' => ['super_admin', 'admin', 'secretaria', 'coordenador'],
        'Gerenciar:SimuladorProximoAno' => ['super_admin', 'admin'],
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

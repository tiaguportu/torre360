<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Quem marca o ponto é toda a equipe; quem enxerga o ponto dos outros, aprova ajustes e configura o módulo é a
     * administração (o ponto é dado pessoal do trabalhador, e cada consulta a dados de outros fica registrada).
     *
     * @var array<string, list<string>>
     */
    private array $permissoes = [
        'Registrar:PontoRegistro' => ['super_admin', 'admin', 'secretaria', 'coordenador', 'professor'],
        'ViewAny:PontoRegistro' => ['super_admin', 'admin'],
        'View:PontoRegistro' => ['super_admin', 'admin'],
        'VerEspelhoDeTodos:PontoRegistro' => ['super_admin', 'admin'],

        'ViewAny:PontoTratamento' => ['super_admin', 'admin', 'secretaria', 'coordenador', 'professor'],
        'View:PontoTratamento' => ['super_admin', 'admin', 'secretaria', 'coordenador', 'professor'],
        'Create:PontoTratamento' => ['super_admin', 'admin', 'secretaria', 'coordenador', 'professor'],
        'Aprovar:PontoTratamento' => ['super_admin', 'admin'],

        'ViewAny:PontoConfiguracao' => ['super_admin', 'admin'],
        'View:PontoConfiguracao' => ['super_admin', 'admin'],
        'Create:PontoConfiguracao' => ['super_admin', 'admin'],
        'Update:PontoConfiguracao' => ['super_admin', 'admin'],

        'ViewAny:PontoJornada' => ['super_admin', 'admin'],
        'View:PontoJornada' => ['super_admin', 'admin'],
        'Create:PontoJornada' => ['super_admin', 'admin'],
        'Update:PontoJornada' => ['super_admin', 'admin'],

        'ViewAny:PontoAcordoBancoHoras' => ['super_admin', 'admin'],
        'View:PontoAcordoBancoHoras' => ['super_admin', 'admin'],
        'Create:PontoAcordoBancoHoras' => ['super_admin', 'admin'],
        'Update:PontoAcordoBancoHoras' => ['super_admin', 'admin'],

        'VerTodos:PontoBancoHorasLancamento' => ['super_admin', 'admin'],
        'Ajustar:PontoBancoHorasLancamento' => ['super_admin', 'admin'],
        'Compensar:PontoBancoHorasLancamento' => ['super_admin', 'admin'],
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

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Autoavaliação e plano de desenvolvimento são dados sensíveis de funcionário. O professor vê e preenche o que é
     * dele (o recorte é feito pela policy e pela consulta); a coordenação e a administração conduzem o plano.
     *
     * @var array<string, list<string>>
     */
    private array $permissoes = [
        'ViewAny:AutoavaliacaoDocente' => ['super_admin', 'admin', 'coordenador', 'professor'],
        'View:AutoavaliacaoDocente' => ['super_admin', 'admin', 'coordenador', 'professor'],
        'Create:AutoavaliacaoDocente' => ['super_admin', 'admin', 'coordenador', 'professor'],
        'Update:AutoavaliacaoDocente' => ['super_admin', 'admin', 'coordenador', 'professor'],
        'Enviar:AutoavaliacaoDocente' => ['super_admin', 'admin', 'coordenador', 'professor'],
        'Reabrir:AutoavaliacaoDocente' => ['super_admin', 'admin'],

        'ViewAny:PlanoDesenvolvimentoDocente' => ['super_admin', 'admin', 'coordenador', 'professor'],
        'View:PlanoDesenvolvimentoDocente' => ['super_admin', 'admin', 'coordenador', 'professor'],
        'Create:PlanoDesenvolvimentoDocente' => ['super_admin', 'admin', 'coordenador'],
        'Update:PlanoDesenvolvimentoDocente' => ['super_admin', 'admin', 'coordenador'],
        'Ativar:PlanoDesenvolvimentoDocente' => ['super_admin', 'admin', 'coordenador'],
        'Concluir:PlanoDesenvolvimentoDocente' => ['super_admin', 'admin', 'coordenador'],
        'Delete:PlanoDesenvolvimentoDocente' => ['super_admin', 'admin'],
        'DarCiencia:PlanoDesenvolvimentoDocente' => ['super_admin', 'admin', 'coordenador', 'professor'],
        'AtualizarAcao:PlanoDesenvolvimentoDocente' => ['super_admin', 'admin', 'coordenador', 'professor'],
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

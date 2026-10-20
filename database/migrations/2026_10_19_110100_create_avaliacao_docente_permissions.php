<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Avaliação de desempenho é dado sensível de funcionário: configurar instrumentos e ciclos e avaliar fica com a
     * coordenação e a administração; o professor só consulta as próprias avaliações finalizadas (o recorte é feito
     * pela policy e pela consulta do Resource). Reabrir e excluir ficam só com as funções administrativas.
     *
     * @var array<string, list<string>>
     */
    private array $permissoes = [
        'ViewAny:InstrumentoAvaliacaoDocente' => ['super_admin', 'admin', 'coordenador'],
        'View:InstrumentoAvaliacaoDocente' => ['super_admin', 'admin', 'coordenador'],
        'Create:InstrumentoAvaliacaoDocente' => ['super_admin', 'admin', 'coordenador'],
        'Update:InstrumentoAvaliacaoDocente' => ['super_admin', 'admin', 'coordenador'],
        'Delete:InstrumentoAvaliacaoDocente' => ['super_admin', 'admin'],
        'DeleteAny:InstrumentoAvaliacaoDocente' => ['super_admin', 'admin'],

        'ViewAny:CicloAvaliacaoDocente' => ['super_admin', 'admin', 'coordenador'],
        'View:CicloAvaliacaoDocente' => ['super_admin', 'admin', 'coordenador'],
        'Create:CicloAvaliacaoDocente' => ['super_admin', 'admin', 'coordenador'],
        'Update:CicloAvaliacaoDocente' => ['super_admin', 'admin', 'coordenador'],
        'Delete:CicloAvaliacaoDocente' => ['super_admin', 'admin'],
        'DeleteAny:CicloAvaliacaoDocente' => ['super_admin', 'admin'],

        'ViewAny:AvaliacaoDocente' => ['super_admin', 'admin', 'coordenador', 'professor'],
        'View:AvaliacaoDocente' => ['super_admin', 'admin', 'coordenador', 'professor'],
        'Create:AvaliacaoDocente' => ['super_admin', 'admin', 'coordenador'],
        'Update:AvaliacaoDocente' => ['super_admin', 'admin', 'coordenador'],
        'Finalizar:AvaliacaoDocente' => ['super_admin', 'admin', 'coordenador'],
        'Reabrir:AvaliacaoDocente' => ['super_admin', 'admin'],
        'Delete:AvaliacaoDocente' => ['super_admin', 'admin'],
        'DeleteAny:AvaliacaoDocente' => ['super_admin', 'admin'],
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

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Notificações ao Conselho Tutelar (Onda 57) e Reposição de aulas (Onda 49) não tinham política: o painel as liberava a qualquer conta
     * ativa. Com as políticas, quem precisa precisa da permissão; esta migration a dá aos papéis que a rotina da escola usa. Papéis que a
     * escola não criou são ignorados; um papel feito à mão precisa recebê-las em Perfis de acesso.
     *
     * @var array<string, list<string>>
     */
    private array $permissoes = [
        'ViewAny:NotificacaoConselhoTutelar' => ['super_admin', 'admin', 'secretaria', 'coordenador'],
        'View:NotificacaoConselhoTutelar' => ['super_admin', 'admin', 'secretaria', 'coordenador'],
        'Create:NotificacaoConselhoTutelar' => ['super_admin', 'admin', 'secretaria', 'coordenador'],
        'Update:NotificacaoConselhoTutelar' => ['super_admin', 'admin', 'secretaria', 'coordenador'],
        'Delete:NotificacaoConselhoTutelar' => ['super_admin', 'admin'],
        'DeleteAny:NotificacaoConselhoTutelar' => ['super_admin', 'admin'],

        'ViewAny:ReposicaoAula' => ['super_admin', 'admin', 'secretaria', 'coordenador', 'professor'],
        'View:ReposicaoAula' => ['super_admin', 'admin', 'secretaria', 'coordenador', 'professor'],
        'Create:ReposicaoAula' => ['super_admin', 'admin', 'secretaria', 'coordenador', 'professor'],
        'Update:ReposicaoAula' => ['super_admin', 'admin', 'secretaria', 'coordenador', 'professor'],
        'Delete:ReposicaoAula' => ['super_admin', 'admin'],
        'DeleteAny:ReposicaoAula' => ['super_admin', 'admin'],
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
        // As permissões podem ter sido criadas antes por outra via (Shield): a migration só retira o que deu aos papéis.
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->permissoes as $nome => $papeis) {
            $permissao = Permission::where('name', $nome)->where('guard_name', 'web')->first();

            if ($permissao === null) {
                continue;
            }

            foreach (Role::whereIn('name', $papeis)->get() as $papel) {
                $papel->revokePermissionTo($permissao);
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};

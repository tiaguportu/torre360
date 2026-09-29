<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Ver a Central de Ajuda e assistir/baixar os vídeos: todo mundo que usa o painel admin.
        $verPermissoes = ['ViewAny:VideoTutorial', 'View:VideoTutorial'];

        // Gerenciar o acervo de vídeos (criar/editar/excluir): só quem cuida do conteúdo.
        $gerenciarPermissoes = ['Create:VideoTutorial', 'Update:VideoTutorial', 'Delete:VideoTutorial', 'DeleteAny:VideoTutorial'];

        foreach ([...$verPermissoes, ...$gerenciarPermissoes] as $permissionName) {
            Permission::firstOrCreate(
                ['name' => $permissionName, 'guard_name' => 'web'],
                ['name' => $permissionName, 'guard_name' => 'web']
            );
        }

        $papeisComVisualizacao = ['professor', 'secretaria', 'coordenador', 'admin', 'super_admin'];
        foreach ($papeisComVisualizacao as $nomePapel) {
            $role = Role::where('name', $nomePapel)->first();
            if (! $role) {
                continue;
            }
            foreach ($verPermissoes as $permissionName) {
                $permission = Permission::where('name', $permissionName)->first();
                if ($permission && ! $role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            }
        }

        $papeisComGerenciamento = ['secretaria', 'admin', 'super_admin'];
        foreach ($papeisComGerenciamento as $nomePapel) {
            $role = Role::where('name', $nomePapel)->first();
            if (! $role) {
                continue;
            }
            foreach ($gerenciarPermissoes as $permissionName) {
                $permission = Permission::where('name', $permissionName)->first();
                if ($permission && ! $role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
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
        //
    }
};

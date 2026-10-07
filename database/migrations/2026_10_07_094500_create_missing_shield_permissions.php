<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $crudActions = [
        'ViewAny',
        'View',
        'Create',
        'Update',
        'Delete',
        'DeleteAny',
        'Restore',
        'ForceDelete',
        'ForceDeleteAny',
        'RestoreAny',
        'Replicate',
        'Reorder',
    ];

    private array $models = [
        'Concorrente',
        'IndicacaoInteressado',
        'MensagemWhatsappTemplate',
        'Objecao',
        'TipoOcorrencia',
    ];

    private array $customPermissions = [
        'View:RelatorioFluxoCaixa',
        'View:RelatorioInadimplencia',
        'AvisarPossibilidadePreceptoria:Matricula',
        'Create:HistoricoContato',
        'Execute:ReguaCobranca',
        'Execute:ReguaFollowUp',
        'UseAssistant',
    ];

    public function up(): void
    {
        $permIdsByName = [];

        // 1. Criar permissões CRUD para os models faltantes
        foreach ($this->models as $model) {
            foreach ($this->crudActions as $action) {
                $name = "{$action}:{$model}";
                $perm = Permission::firstOrCreate([
                    'name' => $name,
                    'guard_name' => 'web',
                ]);
                $permIdsByName[$name] = $perm->id;
            }
        }

        // 2. Criar permissões customizadas e de páginas
        foreach ($this->customPermissions as $name) {
            $perm = Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
            $permIdsByName[$name] = $perm->id;
        }

        // Mapear IDs de Roles
        $roles = Role::whereIn('name', ['super_admin', 'admin', 'secretaria', 'coordenador'])->pluck('id', 'name');

        $rolePermissions = [];

        // 3. super_admin recebe todas as permissões
        if (isset($roles['super_admin'])) {
            $superAdminId = $roles['super_admin'];
            foreach ($permIdsByName as $permId) {
                $rolePermissions[] = [
                    'permission_id' => $permId,
                    'role_id' => $superAdminId,
                ];
            }
        }

        // 4. admin recebe todas as permissões operacionais
        if (isset($roles['admin'])) {
            $adminId = $roles['admin'];
            foreach ($permIdsByName as $permId) {
                $rolePermissions[] = [
                    'permission_id' => $permId,
                    'role_id' => $adminId,
                ];
            }
        }

        // 5. secretaria recebe permissões operacionais pertinentes
        if (isset($roles['secretaria'])) {
            $secretariaId = $roles['secretaria'];
            $secretariaPerms = [
                'View:RelatorioInadimplencia',
                'AvisarPossibilidadePreceptoria:Matricula',
                'Create:HistoricoContato',
                'ViewAny:TipoOcorrencia',
                'View:TipoOcorrencia',
                'Create:TipoOcorrencia',
                'Update:TipoOcorrencia',
                'ViewAny:IndicacaoInteressado',
                'View:IndicacaoInteressado',
                'Create:IndicacaoInteressado',
            ];
            foreach ($secretariaPerms as $sp) {
                if (isset($permIdsByName[$sp])) {
                    $rolePermissions[] = [
                        'permission_id' => $permIdsByName[$sp],
                        'role_id' => $secretariaId,
                    ];
                }
            }
        }

        // 6. coordenador recebe permissões pedagógicas pertinentes
        if (isset($roles['coordenador'])) {
            $coordenadorId = $roles['coordenador'];
            $coordenadorPerms = [
                'AvisarPossibilidadePreceptoria:Matricula',
                'ViewAny:TipoOcorrencia',
                'View:TipoOcorrencia',
                'Create:TipoOcorrencia',
                'Update:TipoOcorrencia',
            ];
            foreach ($coordenadorPerms as $cp) {
                if (isset($permIdsByName[$cp])) {
                    $rolePermissions[] = [
                        'permission_id' => $permIdsByName[$cp],
                        'role_id' => $coordenadorId,
                    ];
                }
            }
        }

        if (! empty($rolePermissions)) {
            DB::table('role_has_permissions')->insertOrIgnore($rolePermissions);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach ($this->models as $model) {
            foreach ($this->crudActions as $action) {
                Permission::where('name', "{$action}:{$model}")->delete();
            }
        }

        foreach ($this->customPermissions as $permName) {
            Permission::where('name', $permName)->delete();
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};

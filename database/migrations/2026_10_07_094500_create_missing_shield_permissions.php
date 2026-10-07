<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
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
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $createdPermissions = [];

        // 1. Criar permissões CRUD para os models faltantes
        foreach ($this->models as $model) {
            foreach ($this->crudActions as $action) {
                $name = "{$action}:{$model}";
                $createdPermissions[] = Permission::firstOrCreate([
                    'name' => $name,
                    'guard_name' => 'web',
                ]);
            }
        }

        // 2. Criar permissões customizadas e de páginas
        foreach ($this->customPermissions as $permName) {
            $createdPermissions[] = Permission::firstOrCreate([
                'name' => $permName,
                'guard_name' => 'web',
            ]);
        }

        // 3. Atribuir ao super_admin (todas)
        $superAdmin = Role::where('name', 'super_admin')->first();
        if ($superAdmin) {
            foreach ($createdPermissions as $permission) {
                if (! $superAdmin->hasPermissionTo($permission)) {
                    $superAdmin->givePermissionTo($permission);
                }
            }
        }

        // 4. Atribuir ao admin (todas operacionais)
        $admin = Role::where('name', 'admin')->first();
        if ($admin) {
            foreach ($createdPermissions as $permission) {
                if (! $admin->hasPermissionTo($permission)) {
                    $admin->givePermissionTo($permission);
                }
            }
        }

        // 5. Atribuir papéis operacionais específicos
        $secretaria = Role::where('name', 'secretaria')->first();
        if ($secretaria) {
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
                $perm = Permission::where('name', $sp)->first();
                if ($perm && ! $secretaria->hasPermissionTo($perm)) {
                    $secretaria->givePermissionTo($perm);
                }
            }
        }

        $coordenador = Role::where('name', 'coordenador')->first();
        if ($coordenador) {
            $coordenadorPerms = [
                'AvisarPossibilidadePreceptoria:Matricula',
                'ViewAny:TipoOcorrencia',
                'View:TipoOcorrencia',
                'Create:TipoOcorrencia',
                'Update:TipoOcorrencia',
            ];
            foreach ($coordenadorPerms as $cp) {
                $perm = Permission::where('name', $cp)->first();
                if ($perm && ! $coordenador->hasPermissionTo($perm)) {
                    $coordenador->givePermissionTo($perm);
                }
            }
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

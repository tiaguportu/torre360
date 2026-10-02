<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesSeeder extends Seeder
{
    /**
     * Cria os papéis padrão do sistema Torre360.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $roles = [
            'super_admin' => 'Super Administrador',
            'admin' => 'Administrador',
            'secretaria' => 'Secretaria',
            'professor' => 'Professor',
            'coordenador' => 'Coordenador',
            'responsavel' => 'Responsável Financeiro',
            'aluno' => 'Aluno',
        ];

        foreach ($roles as $name => $display) {
            Role::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['name' => $name, 'guard_name' => 'web']
            );
        }

        // Atribuir permissões de preceptoria para os papéis aplicáveis
        $permsPreceptoria = [
            Permission::firstOrCreate(['name' => 'ViewAny:Preceptoria', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'View:Preceptoria', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'Agendar:Preceptoria', 'guard_name' => 'web']),
        ];

        foreach (['responsavel', 'aluno', 'secretaria', 'admin'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                foreach ($permsPreceptoria as $perm) {
                    if (! $role->hasPermissionTo($perm)) {
                        $role->givePermissionTo($perm);
                    }
                }
            }
        }

        // Atribuir permissões de widgets do Filament Shield
        $widgetPermissions = [
            'View:AlunosPorTurmaChart',
            'View:ContratosPendentesWidget',
            'View:CrmFollowUpCalendarWidget',
            'View:CronogramaCalendarWidget',
            'View:FrequenciaPendenteWidget',
            'View:InteressadoOrigemChart',
            'View:InteressadoStatusChart',
            'View:MatriculasPendentesWidget',
            'View:PreceptoriaCalendarWidget',
            'View:PreceptoriaSchedulingWidget',
            'View:QuestionariosPendentes',
            'View:QueueSupervisorWidget',
            'View:StatsOverview',
        ];

        foreach ($widgetPermissions as $permName) {
            Permission::firstOrCreate(
                ['name' => $permName, 'guard_name' => 'web'],
                ['name' => $permName, 'guard_name' => 'web']
            );
        }

        $superAdmin = Role::where('name', 'super_admin')->first();
        if ($superAdmin) {
            foreach ($widgetPermissions as $permName) {
                if (! $superAdmin->hasPermissionTo($permName)) {
                    $superAdmin->givePermissionTo($permName);
                }
            }
        }

        // Atribuir permissões da Central de Ajuda (Vídeos Tutoriais)
        $verVideoTutorial = ['ViewAny:VideoTutorial', 'View:VideoTutorial'];
        $gerenciarVideoTutorial = ['Create:VideoTutorial', 'Update:VideoTutorial', 'Delete:VideoTutorial', 'DeleteAny:VideoTutorial'];

        foreach ([...$verVideoTutorial, ...$gerenciarVideoTutorial] as $permName) {
            Permission::firstOrCreate(
                ['name' => $permName, 'guard_name' => 'web'],
                ['name' => $permName, 'guard_name' => 'web']
            );
        }

        foreach (['professor', 'secretaria', 'coordenador', 'admin', 'super_admin'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                foreach ($verVideoTutorial as $permName) {
                    if (! $role->hasPermissionTo($permName)) {
                        $role->givePermissionTo($permName);
                    }
                }
            }
        }

        foreach (['secretaria', 'admin', 'super_admin'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                foreach ($gerenciarVideoTutorial as $permName) {
                    if (! $role->hasPermissionTo($permName)) {
                        $role->givePermissionTo($permName);
                    }
                }
            }
        }

        // Permissões de Histórico Escolar
        $historicoPermissions = [
            'ViewAny:HistoricoEscolar',
            'View:HistoricoEscolar',
            'Create:HistoricoEscolar',
            'Update:HistoricoEscolar',
            'Delete:HistoricoEscolar',
            'DeleteAny:HistoricoEscolar',
        ];

        foreach ($historicoPermissions as $permName) {
            Permission::firstOrCreate(
                ['name' => $permName, 'guard_name' => 'web'],
                ['name' => $permName, 'guard_name' => 'web']
            );
        }

        foreach (['secretaria', 'admin', 'super_admin'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                foreach ($historicoPermissions as $permName) {
                    if (! $role->hasPermissionTo($permName)) {
                        $role->givePermissionTo($permName);
                    }
                }
            }
        }

        // Permissões de Biblioteca Escolar — não é dado sensível, acessível a secretaria,
        // coordenador e professor também.
        $bibliotecaPermissions = [];
        foreach (['Livro', 'Emprestimo'] as $modelName) {
            foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'] as $acao) {
                $bibliotecaPermissions[] = "{$acao}:{$modelName}";
            }
        }

        foreach ($bibliotecaPermissions as $permName) {
            Permission::firstOrCreate(
                ['name' => $permName, 'guard_name' => 'web'],
                ['name' => $permName, 'guard_name' => 'web']
            );
        }

        foreach (['secretaria', 'coordenador', 'professor', 'admin', 'super_admin'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                foreach ($bibliotecaPermissions as $permName) {
                    if (! $role->hasPermissionTo($permName)) {
                        $role->givePermissionTo($permName);
                    }
                }
            }
        }

        // Permissões de RH (Funcionários, Contratos de Trabalho, Férias) — dados sensíveis
        // (salário), restritas a admin/super_admin.
        $rhPermissions = [];
        foreach (['Funcionario', 'ContratoTrabalho', 'PeriodoFerias'] as $modelName) {
            foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'] as $acao) {
                $rhPermissions[] = "{$acao}:{$modelName}";
            }
        }

        foreach ($rhPermissions as $permName) {
            Permission::firstOrCreate(
                ['name' => $permName, 'guard_name' => 'web'],
                ['name' => $permName, 'guard_name' => 'web']
            );
        }

        foreach (['admin', 'super_admin'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                foreach ($rhPermissions as $permName) {
                    if (! $role->hasPermissionTo($permName)) {
                        $role->givePermissionTo($permName);
                    }
                }
            }
        }

        // Permissões de Substituição de Professor — não é dado sensível (financeiro), então
        // secretaria e coordenador também podem gerenciar.
        $substituicaoPermissions = [];
        foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'] as $acao) {
            $substituicaoPermissions[] = "{$acao}:SubstituicaoProfessor";
        }

        foreach ($substituicaoPermissions as $permName) {
            Permission::firstOrCreate(
                ['name' => $permName, 'guard_name' => 'web'],
                ['name' => $permName, 'guard_name' => 'web']
            );
        }

        foreach (['secretaria', 'coordenador', 'admin', 'super_admin'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                foreach ($substituicaoPermissions as $permName) {
                    if (! $role->hasPermissionTo($permName)) {
                        $role->givePermissionTo($permName);
                    }
                }
            }
        }

        $this->command->info('Papéis e permissões criados com sucesso: '.implode(', ', array_keys($roles)));
    }
}

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

        // Permissões de Bolsas e Descontos — dado financeiro, restrito a
        // secretaria/admin/super_admin.
        $bolsaPermissions = [];
        foreach (['TipoBolsa', 'BolsaConcedida'] as $modelName) {
            foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'] as $acao) {
                $bolsaPermissions[] = "{$acao}:{$modelName}";
            }
        }

        foreach ($bolsaPermissions as $permName) {
            Permission::firstOrCreate(
                ['name' => $permName, 'guard_name' => 'web'],
                ['name' => $permName, 'guard_name' => 'web']
            );
        }

        foreach (['secretaria', 'admin', 'super_admin'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                foreach ($bolsaPermissions as $permName) {
                    if (! $role->hasPermissionTo($permName)) {
                        $role->givePermissionTo($permName);
                    }
                }
            }
        }

        // Permissões de Patrimônio Escolar — não é dado sensível, restrito a
        // secretaria/admin/super_admin (quem cuida do inventário físico).
        $patrimonioPermissions = [];
        foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'] as $acao) {
            $patrimonioPermissions[] = "{$acao}:BemPatrimonial";
        }

        foreach ($patrimonioPermissions as $permName) {
            Permission::firstOrCreate(
                ['name' => $permName, 'guard_name' => 'web'],
                ['name' => $permName, 'guard_name' => 'web']
            );
        }

        foreach (['secretaria', 'admin', 'super_admin'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                foreach ($patrimonioPermissions as $permName) {
                    if (! $role->hasPermissionTo($permName)) {
                        $role->givePermissionTo($permName);
                    }
                }
            }
        }

        // Permissões de Propostas Comerciais e Revenue Management
        $propostaPermissions = [];
        foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny', 'Aprovar'] as $acao) {
            $propostaPermissions[] = "{$acao}:PropostaComercial";
        }
        $propostaPermissions[] = 'View:ControladoriaTurmas';
        $propostaPermissions[] = 'Manage:ControladoriaTurmas';

        foreach ($propostaPermissions as $permName) {
            Permission::firstOrCreate(
                ['name' => $permName, 'guard_name' => 'web'],
                ['name' => $permName, 'guard_name' => 'web']
            );
        }

        foreach (['secretaria', 'coordenador', 'admin', 'super_admin'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                foreach ($propostaPermissions as $permName) {
                    if (! $role->hasPermissionTo($permName)) {
                        $role->givePermissionTo($permName);
                    }
                }
            }
        }

        // Permissões de Lei da Mensalidade Escolar (Lei 9.870/99) e Acordos de Inadimplência
        $financeiroNovoPermissions = [];
        foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny', 'Homologar'] as $acao) {
            $financeiroNovoPermissions[] = "{$acao}:PlanilhaLeiMensalidade";
        }
        foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny', 'Aprovar'] as $acao) {
            $financeiroNovoPermissions[] = "{$acao}:AcordoInadimplencia";
        }

        foreach ($financeiroNovoPermissions as $permName) {
            Permission::firstOrCreate(
                ['name' => $permName, 'guard_name' => 'web'],
                ['name' => $permName, 'guard_name' => 'web']
            );
        }

        foreach (['secretaria', 'admin', 'super_admin'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                foreach ($financeiroNovoPermissions as $permName) {
                    if (! $role->hasPermissionTo($permName)) {
                        $role->givePermissionTo($permName);
                    }
                }
            }
        }

        // Permissões de CRM, Ocorrências, Relatórios e Réguas do Filament Shield
        $novasShieldPermissions = [];
        $modelsNovos = ['Concorrente', 'IndicacaoInteressado', 'MensagemWhatsappTemplate', 'Objecao', 'TipoOcorrencia'];
        $acoesCrud = ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'];

        foreach ($modelsNovos as $modelName) {
            foreach ($acoesCrud as $acao) {
                $novasShieldPermissions[] = "{$acao}:{$modelName}";
            }
        }

        $customShieldPerms = [
            'View:RelatorioFluxoCaixa',
            'View:RelatorioInadimplencia',
            'AvisarPossibilidadePreceptoria:Matricula',
            'Create:HistoricoContato',
            'Execute:ReguaCobranca',
            'Execute:ReguaFollowUp',
            'UseAssistant',
        ];

        foreach (array_merge($novasShieldPermissions, $customShieldPerms) as $permName) {
            Permission::firstOrCreate(
                ['name' => $permName, 'guard_name' => 'web'],
                ['name' => $permName, 'guard_name' => 'web']
            );
        }

        // super_admin e admin recebem todas
        foreach (['admin', 'super_admin'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                foreach (array_merge($novasShieldPermissions, $customShieldPerms) as $permName) {
                    if (! $role->hasPermissionTo($permName)) {
                        $role->givePermissionTo($permName);
                    }
                }
            }
        }

        // secretaria recebe operacionais de atendimento, relatórios e matrículas
        $secretaria = Role::where('name', 'secretaria')->first();
        if ($secretaria) {
            $secretariaExtraPerms = [
                'View:RelatorioInadimplencia',
                'AvisarPossibilidadePreceptoria:Matricula',
                'Create:HistoricoContato',
                'ViewAny:TipoOcorrencia', 'View:TipoOcorrencia', 'Create:TipoOcorrencia', 'Update:TipoOcorrencia',
                'ViewAny:IndicacaoInteressado', 'View:IndicacaoInteressado', 'Create:IndicacaoInteressado',
            ];
            foreach ($secretariaExtraPerms as $permName) {
                if (! $secretaria->hasPermissionTo($permName)) {
                    $secretaria->givePermissionTo($permName);
                }
            }
        }

        // coordenador recebe pedagógicas e de acompanhamento
        $coordenador = Role::where('name', 'coordenador')->first();
        if ($coordenador) {
            $coordenadorExtraPerms = [
                'AvisarPossibilidadePreceptoria:Matricula',
                'ViewAny:TipoOcorrencia', 'View:TipoOcorrencia', 'Create:TipoOcorrencia', 'Update:TipoOcorrencia',
            ];
            foreach ($coordenadorExtraPerms as $permName) {
                if (! $coordenador->hasPermissionTo($permName)) {
                    $coordenador->givePermissionTo($permName);
                }
            }
        }

        $this->command->info('Papéis e permissões criados com sucesso: '.implode(', ', array_keys($roles)));
    }
}

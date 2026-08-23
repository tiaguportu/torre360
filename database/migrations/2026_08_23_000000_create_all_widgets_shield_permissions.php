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

        foreach ($widgetPermissions as $permissionName) {
            Permission::firstOrCreate(
                ['name' => $permissionName, 'guard_name' => 'web'],
                ['name' => $permissionName, 'guard_name' => 'web']
            );
        }

        // Concede as permissões ao papel super_admin inicialmente, permitindo que sejam customizadas no Shield
        $superAdmin = Role::where('name', 'super_admin')->first();
        if ($superAdmin) {
            foreach ($widgetPermissions as $permissionName) {
                $permission = Permission::where('name', $permissionName)->first();
                if ($permission && ! $superAdmin->hasPermissionTo($permission)) {
                    $superAdmin->givePermissionTo($permission);
                }
            }
        }

        // Concede permissões pertinentes aos outros papéis administrativos / específicos
        $admin = Role::where('name', 'admin')->first();
        if ($admin) {
            $adminWidgets = [
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
                'View:StatsOverview',
            ];
            foreach ($adminWidgets as $permissionName) {
                $permission = Permission::where('name', $permissionName)->first();
                if ($permission && ! $admin->hasPermissionTo($permission)) {
                    $admin->givePermissionTo($permission);
                }
            }
        }

        $secretaria = Role::where('name', 'secretaria')->first();
        if ($secretaria) {
            $secretariaWidgets = [
                'View:AlunosPorTurmaChart',
                'View:ContratosPendentesWidget',
                'View:CronogramaCalendarWidget',
                'View:FrequenciaPendenteWidget',
                'View:MatriculasPendentesWidget',
                'View:PreceptoriaCalendarWidget',
                'View:QuestionariosPendentes',
                'View:StatsOverview',
            ];
            foreach ($secretariaWidgets as $permissionName) {
                $permission = Permission::where('name', $permissionName)->first();
                if ($permission && ! $secretaria->hasPermissionTo($permission)) {
                    $secretaria->givePermissionTo($permission);
                }
            }
        }

        $professor = Role::where('name', 'professor')->first();
        if ($professor) {
            $professorWidgets = [
                'View:CronogramaCalendarWidget',
                'View:FrequenciaPendenteWidget',
                'View:PreceptoriaCalendarWidget',
                'View:QuestionariosPendentes',
            ];
            foreach ($professorWidgets as $permissionName) {
                $permission = Permission::where('name', $permissionName)->first();
                if ($permission && ! $professor->hasPermissionTo($permission)) {
                    $professor->givePermissionTo($permission);
                }
            }
        }

        $responsavel = Role::where('name', 'responsavel')->first();
        if ($responsavel) {
            $responsavelWidgets = [
                'View:ContratosPendentesWidget',
                'View:PreceptoriaSchedulingWidget',
                'View:QuestionariosPendentes',
            ];
            foreach ($responsavelWidgets as $permissionName) {
                $permission = Permission::where('name', $permissionName)->first();
                if ($permission && ! $responsavel->hasPermissionTo($permission)) {
                    $responsavel->givePermissionTo($permission);
                }
            }
        }

        $aluno = Role::where('name', 'aluno')->first();
        if ($aluno) {
            $alunoWidgets = [
                'View:ContratosPendentesWidget',
                'View:PreceptoriaSchedulingWidget',
                'View:QuestionariosPendentes',
            ];
            foreach ($alunoWidgets as $permissionName) {
                $permission = Permission::where('name', $permissionName)->first();
                if ($permission && ! $aluno->hasPermissionTo($permission)) {
                    $aluno->givePermissionTo($permission);
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

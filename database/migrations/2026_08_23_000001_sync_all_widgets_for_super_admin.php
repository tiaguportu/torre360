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

        // Garante que todas as permissões existam
        foreach ($widgetPermissions as $permissionName) {
            Permission::firstOrCreate(
                ['name' => $permissionName, 'guard_name' => 'web'],
                ['name' => $permissionName, 'guard_name' => 'web']
            );
        }

        // Concede todas as permissões de widgets ao super_admin por padrão
        $superAdmin = Role::where('name', 'super_admin')->first();
        if ($superAdmin) {
            foreach ($widgetPermissions as $permissionName) {
                $permission = Permission::where('name', $permissionName)->first();
                if ($permission) {
                    $superAdmin->givePermissionTo($permission);
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

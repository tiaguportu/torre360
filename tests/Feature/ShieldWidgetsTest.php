<?php

namespace Tests\Feature;

use App\Filament\Widgets\AlunosPorTurmaChart;
use App\Filament\Widgets\ContratosPendentesWidget;
use App\Filament\Widgets\CrmFollowUpCalendarWidget;
use App\Filament\Widgets\CronogramaCalendarWidget;
use App\Filament\Widgets\FrequenciaPendenteWidget;
use App\Filament\Widgets\InteressadoOrigemChart;
use App\Filament\Widgets\InteressadoStatusChart;
use App\Filament\Widgets\MatriculasPendentesWidget;
use App\Filament\Widgets\PreceptoriaCalendarWidget;
use App\Filament\Widgets\PreceptoriaSchedulingWidget;
use App\Filament\Widgets\QuestionariosPendentes;
use App\Filament\Widgets\QueueSupervisorWidget;
use App\Filament\Widgets\StatsOverview;
use App\Models\Questionario;
use App\Models\User;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ShieldWidgetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function test_shield_discovers_all_application_widgets(): void
    {
        $widgets = FilamentShield::getWidgets();

        $this->assertIsArray($widgets);

        $expectedWidgets = [
            AlunosPorTurmaChart::class,
            ContratosPendentesWidget::class,
            CrmFollowUpCalendarWidget::class,
            CronogramaCalendarWidget::class,
            FrequenciaPendenteWidget::class,
            InteressadoOrigemChart::class,
            InteressadoStatusChart::class,
            MatriculasPendentesWidget::class,
            PreceptoriaCalendarWidget::class,
            PreceptoriaSchedulingWidget::class,
            QuestionariosPendentes::class,
            QueueSupervisorWidget::class,
            StatsOverview::class,
        ];

        foreach ($expectedWidgets as $widgetClass) {
            $this->assertArrayHasKey($widgetClass, $widgets, "Widget {$widgetClass} não foi descoberto pelo Shield.");
            $permissions = $widgets[$widgetClass]['permissions'] ?? [];
            $this->assertNotEmpty($permissions, "Widget {$widgetClass} não possui permissões mapeadas.");

            $permissionKey = array_key_first($permissions);
            $expectedKey = 'View:'.class_basename($widgetClass);
            $this->assertEquals($expectedKey, $permissionKey, "A chave de permissão gerada para {$widgetClass} deveria ser {$expectedKey}");
        }
    }

    public function test_super_admin_cannot_view_widget_if_permission_is_revoked_in_shield(): void
    {
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $statsPermission = Permission::firstOrCreate(['name' => 'View:StatsOverview', 'guard_name' => 'web']);
        $chartPermission = Permission::firstOrCreate(['name' => 'View:AlunosPorTurmaChart', 'guard_name' => 'web']);

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole($superAdminRole);

        $this->actingAs($superAdmin);
        session(['active_role' => 'super_admin']);

        // 1. Inicialmente, atribuímos as permissões à role super_admin
        $superAdminRole->givePermissionTo($statsPermission);
        $superAdminRole->givePermissionTo($chartPermission);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->assertTrue(StatsOverview::canView(), 'Super Admin deveria ver StatsOverview quando a permissão estiver marcada.');
        $this->assertTrue(AlunosPorTurmaChart::canView(), 'Super Admin deveria ver AlunosPorTurmaChart quando a permissão estiver marcada.');

        // 2. Desmarcamos/Revogamos StatsOverview da role super_admin
        $superAdminRole->revokePermissionTo($statsPermission);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->assertFalse(StatsOverview::canView(), 'Super Admin NÃO deveria ver StatsOverview se a permissão foi desmarcada no Shield.');
        $this->assertTrue(AlunosPorTurmaChart::canView(), 'Super Admin ainda deve ver AlunosPorTurmaChart pois sua permissão continua ativa.');

        // 3. Desmarcamos/Revogamos AlunosPorTurmaChart também
        $superAdminRole->revokePermissionTo($chartPermission);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->assertFalse(AlunosPorTurmaChart::canView(), 'Super Admin NÃO deveria ver AlunosPorTurmaChart após desmarcação.');
    }

    public function test_other_roles_respect_widget_shield_permissions(): void
    {
        $secretariaRole = Role::firstOrCreate(['name' => 'secretaria', 'guard_name' => 'web']);
        $matriculasPermission = Permission::firstOrCreate(['name' => 'View:MatriculasPendentesWidget', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($secretariaRole);

        $this->actingAs($user);
        session(['active_role' => 'secretaria']);

        // Sem permissão
        $this->assertFalse(MatriculasPendentesWidget::canView());

        // Com permissão
        $secretariaRole->givePermissionTo($matriculasPermission);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->assertTrue(MatriculasPendentesWidget::canView());
    }

    public function test_questionarios_widget_requires_both_shield_permission_and_pending_questionnaires(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $questionarioPerm = Permission::firstOrCreate(['name' => 'View:QuestionariosPendentes', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($adminRole);

        $this->actingAs($user);
        session(['active_role' => 'admin']);

        // Caso 1: Sem permissão do Shield e sem questionários
        $this->assertFalse(QuestionariosPendentes::canView());

        // Caso 2: Com permissão do Shield, mas sem questionários pendentes
        $adminRole->givePermissionTo($questionarioPerm);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->assertFalse(QuestionariosPendentes::canView(), 'Não deve exibir widget se não houver questionários pendentes mesmo com permissão Shield.');

        // Caso 3: Com permissão do Shield E com questionário pendente elegível
        Questionario::create([
            'titulo' => 'Pesquisa de Satisfação',
            'is_ativo' => true,
            'is_anonimo' => true,
            'publico_alvo' => 'todos',
            'created_by' => $user->id,
        ]);

        $this->assertTrue(QuestionariosPendentes::canView(), 'Deve exibir o widget quando houver questionário pendente e permissão Shield.');

        // Caso 4: Revoga permissão Shield do role, mesmo com questionário ativo
        $adminRole->revokePermissionTo($questionarioPerm);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->assertFalse(QuestionariosPendentes::canView(), 'NÃO deve exibir o widget se o Shield não conceder a permissão, mesmo com questionários pendentes.');
    }
}

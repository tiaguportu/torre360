<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Widgets\InteressadoOrigemChart;
use App\Filament\Widgets\InteressadoStatusChart;
use App\Models\Interessado;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

class InteressadoChartsEscopoTest extends TestCase
{
    use ProibeLazyLoading;
    use RefreshDatabase;

    private StatusInteressado $etapa1;

    private StatusInteressado $etapa2;

    private OrigemInteressado $origemSite;

    private OrigemInteressado $origemIndicacao;

    protected function setUp(): void
    {
        parent::setUp();

        $this->etapa1 = StatusInteressado::create([
            'nome' => 'Primeira Etapa',
            'cor' => 'info',
            'ordem' => 1,
            'is_final' => false,
            'is_ganho' => false,
        ]);

        $this->etapa2 = StatusInteressado::create([
            'nome' => 'Segunda Etapa',
            'cor' => 'warning',
            'ordem' => 2,
            'is_final' => false,
            'is_ganho' => false,
        ]);

        $this->origemSite = OrigemInteressado::create(['nome' => 'Site']);
        $this->origemIndicacao = OrigemInteressado::create(['nome' => 'Indicacao']);
    }

    private function getChartData(object $widget): array
    {
        $reflection = new ReflectionMethod($widget, 'getData');
        $reflection->setAccessible(true);

        return $reflection->invoke($widget);
    }

    private function criarLead(?User $consultor, StatusInteressado $status, OrigemInteressado $origem): Interessado
    {
        $pessoa = Pessoa::factory()->create();

        return Interessado::create([
            'pessoa_id' => $pessoa->id,
            'status_interessado_id' => $status->id,
            'origem_interessado_id' => $origem->id,
            'usuario_id' => $consultor?->id,
        ]);
    }

    public function test_graficos_respeitam_escopo_de_carteira_do_consultor_e_acesso_amplo(): void
    {
        $consultorA = User::factory()->create(['name' => 'Consultor A']);
        $consultorB = User::factory()->create(['name' => 'Consultor B']);

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin = User::factory()->create(['name' => 'Super Admin']);
        $superAdmin->assignRole('super_admin');

        $gestor = User::factory()->create(['name' => 'Gestor']);
        $gestor->givePermissionTo(
            Permission::firstOrCreate(['name' => 'ViewAll:Interessado', 'guard_name' => 'web'])
        );

        // Leads consultor A:
        $this->criarLead($consultorA, $this->etapa1, $this->origemSite);
        $this->criarLead($consultorA, $this->etapa2, $this->origemIndicacao);

        // Leads consultor B:
        $this->criarLead($consultorB, $this->etapa1, $this->origemSite);
        $this->criarLead($consultorB, $this->etapa2, $this->origemSite);

        // Lead sem dono:
        $this->criarLead(null, $this->etapa1, $this->origemIndicacao);

        // Consultor A: deve ver 2 leads na etapa1 (próprio + sem dono) e 1 na etapa2 (próprio)
        $this->actingAs($consultorA);
        $statusChartA = new InteressadoStatusChart;
        $statusDataA = $this->getChartData($statusChartA);

        $this->assertSame(['Primeira Etapa', 'Segunda Etapa'], $statusDataA['labels']);
        $this->assertSame([2, 1], $statusDataA['datasets'][0]['data']);

        // Consultor A: Origem: Site => 1 (próprio), Indicacao => 2 (1 próprio + 1 sem dono)
        $origemChartA = new InteressadoOrigemChart;
        $origemDataA = $this->getChartData($origemChartA);
        $origemMapA = array_combine($origemDataA['labels'], $origemDataA['datasets'][0]['data']);
        $this->assertSame(1, $origemMapA['Site'] ?? null);
        $this->assertSame(2, $origemMapA['Indicacao'] ?? null);

        // Super Admin: deve ver todos os 5 leads (3 na etapa1, 2 na etapa2)
        $this->actingAs($superAdmin);
        $statusChartSuper = new InteressadoStatusChart;
        $statusDataSuper = $this->getChartData($statusChartSuper);
        $this->assertSame(['Primeira Etapa', 'Segunda Etapa'], $statusDataSuper['labels']);
        $this->assertSame([3, 2], $statusDataSuper['datasets'][0]['data']);

        // Gestor com ViewAll:Interessado: deve ver todos os 5 leads
        $this->actingAs($gestor);
        $statusChartGestor = new InteressadoStatusChart;
        $statusDataGestor = $this->getChartData($statusChartGestor);
        $this->assertSame(['Primeira Etapa', 'Segunda Etapa'], $statusDataGestor['labels']);
        $this->assertSame([3, 2], $statusDataGestor['datasets'][0]['data']);
    }

    public function test_grafico_status_mantem_ordem_das_etapas_com_escopo(): void
    {
        $consultor = User::factory()->create();

        // Inverter a criação das etapas ou garantir que a ordem é estritamente pela coluna 'ordem'
        $this->criarLead($consultor, $this->etapa2, $this->origemSite);
        $this->criarLead($consultor, $this->etapa1, $this->origemSite);

        $this->actingAs($consultor);
        $widget = new InteressadoStatusChart;
        $data = $this->getChartData($widget);

        $this->assertSame(['Primeira Etapa', 'Segunda Etapa'], $data['labels']);
        $this->assertSame([1, 1], $data['datasets'][0]['data']);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\StatusPlanilhaLei;
use App\Filament\Resources\PlanilhaLeiMensalidades\Pages\ListPlanilhaLeiMensalidades;
use App\Models\PlanilhaLeiMensalidade;
use App\Models\User;
use App\Services\PlanilhaLeiMensalidadeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PlanilhaLeiMensalidadeTest extends TestCase
{
    use RefreshDatabase;

    private function autenticarComo(string $roleName = 'admin'): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $perms = [
            'ViewAny:PlanilhaLeiMensalidade',
            'View:PlanilhaLeiMensalidade',
            'Create:PlanilhaLeiMensalidade',
            'Update:PlanilhaLeiMensalidade',
            'Delete:PlanilhaLeiMensalidade',
            'DeleteAny:PlanilhaLeiMensalidade',
            'Homologar:PlanilhaLeiMensalidade',
        ];

        foreach ($perms as $p) {
            $perm = Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
            $role->givePermissionTo($perm);
        }

        $user = User::factory()->create([
            'activated_at' => now()->subDay(),
            'email_verified_at' => now(),
        ]);
        $user->assignRole($roleName);
        $this->actingAs($user);
        session(['active_role' => $roleName]);

        return $user;
    }

    public function test_calculo_da_formula_da_lei_9870_correto(): void
    {
        $service = app(PlanilhaLeiMensalidadeService::class);

        $dados = [
            'alunos_base' => 100,
            'mensalidade_media_base' => 1000.00,
            'custo_pessoal_base' => 600000.00,
            'custo_custeio_base' => 300000.00,
            'custo_investimento_base' => 100000.00,
            'percentual_dissidio_pessoal' => 6.00,     // +36.000
            'percentual_inflacao_custeio' => 5.00,     // +15.000
            'valor_novos_investimentos' => 49000.00,   // +49.000
            'percentual_reajuste_adotado' => 10.00,
        ];

        $resultado = $service->calcularIndices($dados);

        // Custo total base: 600.000 + 300.000 + 100.000 = 1.000.000
        $this->assertEquals(1000000.00, $resultado['custo_total_base']);

        // Variações: +36.000 (pessoal) + 15.000 (custeio) + 49.000 (novos invest) = +100.000
        // Custo projetado total: 1.100.000
        $this->assertEquals(1100000.00, $resultado['custo_total_projetado']);

        // Índice da Lei: (1.100.000 - 1.000.000) / 1.000.000 * 100 = 10.00%
        $this->assertEquals(10.00, $resultado['variacao_custo_total_percentual']);
        $this->assertEquals(10.00, $resultado['percentual_reajuste_sugerido']);

        // Mensalidade projetada: 1000 * 1.10 = 1100
        $this->assertEquals(1100.00, $resultado['mensalidade_projetada']);
        $this->assertEquals(13200.00, $resultado['anuidade_projetada']);
    }

    public function test_geracao_de_espelho_oficial_html_contem_elementos_legais(): void
    {
        $user = $this->autenticarComo('admin');

        $planilha = PlanilhaLeiMensalidade::create([
            'ano_base' => 2026,
            'ano_letivo_destino' => 2027,
            'titulo' => 'Planilha de Custos 2027',
            'alunos_base' => 120,
            'mensalidade_media_base' => 850.00,
            'custo_pessoal_base' => 500000.00,
            'custo_custeio_base' => 250000.00,
            'custo_investimento_base' => 50000.00,
            'custo_total_base' => 800000.00,
            'percentual_dissidio_pessoal' => 5.50,
            'percentual_inflacao_custeio' => 4.50,
            'custo_pessoal_projetado' => 527500.00,
            'custo_custeio_projetado' => 261250.00,
            'custo_investimento_projetado' => 70000.00,
            'custo_total_projetado' => 858750.00,
            'variacao_custo_total_percentual' => 7.34,
            'percentual_reajuste_sugerido' => 7.34,
            'percentual_reajuste_adotado' => 7.50,
            'mensalidade_projetada' => 913.75,
            'anuidade_projetada' => 10965.00,
            'status' => StatusPlanilhaLei::Homologada,
            'justificativa_pedagogica' => 'Implantação de novas lousas digitais e robótica.',
            'responsavel_user_id' => $user->id,
        ]);

        $service = app(PlanilhaLeiMensalidadeService::class);
        $html = $service->gerarEspelhoOficialHtml($planilha);

        $this->assertStringContainsString('DEMONSTRATIVO DE VARIAÇÃO DE CUSTOS', $html);
        $this->assertStringContainsString('Lei Federal nº 9.870/1999', $html);
        $this->assertStringContainsString('Implantação de novas lousas digitais', $html);
        $this->assertStringContainsString('7,5', $html);
    }

    public function test_listagem_de_planilhas_carrega_com_sucesso(): void
    {
        $user = $this->autenticarComo('admin');

        PlanilhaLeiMensalidade::create([
            'ano_base' => 2026,
            'ano_letivo_destino' => 2027,
            'titulo' => 'Planilha Matriz 2027',
            'alunos_base' => 80,
            'mensalidade_media_base' => 900.00,
            'custo_total_base' => 500000.00,
            'custo_total_projetado' => 540000.00,
            'variacao_custo_total_percentual' => 8.00,
            'percentual_reajuste_adotado' => 8.00,
            'mensalidade_projetada' => 972.00,
            'status' => StatusPlanilhaLei::Rascunho,
            'responsavel_user_id' => $user->id,
        ]);

        Livewire::test(ListPlanilhaLeiMensalidades::class)
            ->assertSuccessful()
            ->assertSee('Planilha Matriz 2027');
    }
}

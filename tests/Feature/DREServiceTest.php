<?php

namespace Tests\Feature;

use App\Filament\Pages\RelatorioDRE;
use App\Models\Banco;
use App\Models\PlanoConta;
use App\Models\TransacaoBancaria;
use App\Models\User;
use App\Services\DREService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DREServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $usuarioSemPermissao;

    private Banco $banco;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'aluno', 'guard_name' => 'web']);

        $this->superAdmin = User::factory()->create([
            'activated_at' => now(),
            'email_verified_at' => now(),
        ]);
        $this->superAdmin->assignRole('super_admin');

        $this->usuarioSemPermissao = User::factory()->create([
            'activated_at' => now(),
            'email_verified_at' => now(),
        ]);
        $this->usuarioSemPermissao->assignRole('aluno');

        $this->banco = Banco::create([
            'nome' => 'Banco do Brasil',
            'is_active' => true,
        ]);
    }

    public function test_dreservice_calcula_hierarquia_e_totais_de_receitas_e_despesas(): void
    {
        // 1. Cria árvore de plano de contas de RECEITA
        $planoReceitaPai = PlanoConta::create([
            'codigo' => '1',
            'nome' => 'Receitas Operacionais',
            'tipo' => 'receita',
            'pai_id' => null,
            'is_active' => true,
        ]);

        $planoMensalidade = PlanoConta::create([
            'codigo' => '1.1',
            'nome' => 'Mensalidades Escolares',
            'tipo' => 'receita',
            'pai_id' => $planoReceitaPai->id,
            'is_active' => true,
        ]);

        $planoMatricula = PlanoConta::create([
            'codigo' => '1.2',
            'nome' => 'Taxas de Matrícula',
            'tipo' => 'receita',
            'pai_id' => $planoReceitaPai->id,
            'is_active' => true,
        ]);

        // 2. Cria árvore de plano de contas de DESPESA
        $planoDespesaPai = PlanoConta::create([
            'codigo' => '2',
            'nome' => 'Despesas Operacionais',
            'tipo' => 'despesa',
            'pai_id' => null,
            'is_active' => true,
        ]);

        $planoSalarios = PlanoConta::create([
            'codigo' => '2.1',
            'nome' => 'Salários e Encargos',
            'tipo' => 'despesa',
            'pai_id' => $planoDespesaPai->id,
            'is_active' => true,
        ]);

        // 3. Cria transações bancárias dentro do período
        TransacaoBancaria::create([
            'banco_id' => $this->banco->id,
            'plano_conta_id' => $planoMensalidade->id,
            'tipo' => 'entrada',
            'valor' => 15000.00,
            'data_transacao' => '2026-05-10',
            'conciliado' => true,
        ]);

        TransacaoBancaria::create([
            'banco_id' => $this->banco->id,
            'plano_conta_id' => $planoMatricula->id,
            'tipo' => 'entrada',
            'valor' => 5000.00,
            'data_transacao' => '2026-05-15',
            'conciliado' => true,
        ]);

        TransacaoBancaria::create([
            'banco_id' => $this->banco->id,
            'plano_conta_id' => $planoSalarios->id,
            'tipo' => 'saida',
            'valor' => 8000.00,
            'data_transacao' => '2026-05-20',
            'conciliado' => true,
        ]);

        // Transação fora do período (não deve entrar no cálculo)
        TransacaoBancaria::create([
            'banco_id' => $this->banco->id,
            'plano_conta_id' => $planoMensalidade->id,
            'tipo' => 'entrada',
            'valor' => 2000.00,
            'data_transacao' => '2026-06-01',
            'conciliado' => true,
        ]);

        $service = app(DREService::class);
        $resultado = $service->generate('2026-05-01', '2026-05-31');

        $this->assertEquals(20000.00, $resultado['resumo']['total_receitas']);
        $this->assertEquals(8000.00, $resultado['resumo']['total_despesas']);
        $this->assertEquals(12000.00, $resultado['resumo']['resultado_liquido']);

        // Verifica consolidação do grupo pai
        $grupoReceita = collect($resultado['receitas'])->firstWhere('id', $planoReceitaPai->id);
        $this->assertNotNull($grupoReceita);
        $this->assertEquals(20000.00, $grupoReceita['total']);
        $this->assertCount(2, $grupoReceita['filhos']);
    }

    public function test_usuario_sem_permissao_nao_acessa_pagina_dre(): void
    {
        $this->actingAs($this->usuarioSemPermissao);
        session(['active_role' => 'aluno']);

        $this->get('/admin/financeiro/dre')->assertForbidden();

        Livewire::test(RelatorioDRE::class)->assertForbidden();
    }

    public function test_super_admin_acessa_e_renderiza_pagina_relatorio_dre(): void
    {
        $this->actingAs($this->superAdmin);
        session(['active_role' => 'super_admin']);

        $this->get('/admin/financeiro/dre')->assertSuccessful();

        $component = Livewire::test(RelatorioDRE::class)
            ->assertSuccessful();

        $this->assertNotNull($component->instance()->dreData);
        $this->assertArrayHasKey('resumo', $component->instance()->dreData);
    }
}

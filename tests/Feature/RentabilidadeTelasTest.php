<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\RentabilidadeRealizada;
use App\Filament\Resources\CentroCustos\Pages\EditCentroCusto;
use App\Filament\Resources\CentroCustos\Pages\ListCentroCustos;
use App\Models\Banco;
use App\Models\CentroCusto;
use App\Models\Contrato;
use App\Models\Curso;
use App\Models\Fatura;
use App\Models\ItemFatura;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\PlanoConta;
use App\Models\Serie;
use App\Models\TransacaoBancaria;
use App\Models\Turma;
use App\Models\Unidade;
use App\Models\User;
use App\Services\Pendencias\Fontes\TurmasComResultadoNegativo;
use App\Services\Relatorios\Fontes\RentabilidadeTurmas;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

/**
 * Rentabilidade realizada por turma (Onda 77), telas: a página do resultado, o vínculo do centro de custo, a fila da Minha Mesa e a
 * fonte dos relatórios.
 *
 * "Hoje" é segunda-feira, 26/10/2026. A turma A rende 1.000 por mês e tem 1.500 de custo direto: perde 500 (margem de -50%).
 */
class RentabilidadeTelasTest extends TestCase
{
    use ProibeLazyLoading;
    use RefreshDatabase;

    private User $financeiro;

    private User $leitor;

    private Turma $turma;

    private Curso $curso;

    private CentroCusto $salaA;

    private Contrato $contrato;

    private PlanoConta $despesa;

    private Banco $banco;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-26 09:00:00'));
        Cache::flush();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['financeiro', 'leitor'] as $papel) {
            Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        }

        foreach (['View:RentabilidadeRealizada', 'ViewAny:CentroCusto', 'View:CentroCusto', 'Update:CentroCusto'] as $nome) {
            Role::findByName('financeiro')->givePermissionTo(Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']));
        }

        $this->financeiro = $this->usuario('financeiro');
        $this->leitor = $this->usuario('leitor');

        $unidade = Unidade::create(['nome' => 'Unidade Central', 'situacao_funcionamento' => '1']);
        $this->curso = Curso::create(['unidade_id' => $unidade->id, 'nome_externo' => 'Ensino Fundamental', 'nome_interno' => 'EF']);
        $periodo = PeriodoLetivo::create(['nome' => '2026', 'ano' => 2026, 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15']);
        $this->turma = Turma::create(['nome' => 'Turma A', 'periodo_letivo_id' => $periodo->id, 'serie_id' => Serie::create(['curso_id' => $this->curso->id, 'nome' => '8º Ano', 'sistema_avaliacao' => 'Nota'])->id]);

        $matricula = Matricula::create(['pessoa_id' => Pessoa::create(['nome' => 'Aluno A'])->id, 'turma_id' => $this->turma->id, 'situacao' => 'ativa']);
        $this->contrato = Contrato::create(['matricula_id' => $matricula->id, 'valor_total' => 12000, 'data_aceite' => '2026-01-05']);
        $this->salaA = CentroCusto::create(['nome' => 'Sala A', 'turma_id' => $this->turma->id]);
        $this->despesa = PlanoConta::create(['codigo' => '3.1', 'nome' => 'Despesas', 'tipo' => 'despesa']);
        $this->banco = Banco::create(['nome' => 'Banco Teste', 'is_active' => true]);
    }

    private function usuario(string $papel): User
    {
        $usuario = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $usuario->assignRole($papel);

        return $usuario;
    }

    private function como(User $usuario): User
    {
        session(['active_role' => $usuario->getRoleNames()->first()]);

        return $usuario;
    }

    /**
     * Um mês em que a turma A fatura 1.000 (pagos) e tem 1.500 de custo direto.
     */
    private function mesNegativo(string $mes): void
    {
        $fatura = Fatura::create(['contrato_id' => $this->contrato->id, 'vencimento' => "{$mes}-10", 'status' => 'pago']);
        ItemFatura::create(['fatura_id' => $fatura->id, 'descricao' => 'Mensalidade', 'quantidade' => 1, 'valor_unitario' => 1000, 'desconto' => 0, 'tipo_desconto' => 'absoluto']);
        TransacaoBancaria::create(['banco_id' => $this->banco->id, 'fatura_id' => $fatura->id, 'tipo' => 'entrada', 'valor' => 1000, 'data_transacao' => "{$mes}-10", 'descricao' => 'Pagamento', 'conciliado' => true]);
        TransacaoBancaria::create(['banco_id' => $this->banco->id, 'plano_conta_id' => $this->despesa->id, 'centro_custo_id' => $this->salaA->id, 'tipo' => 'saida', 'valor' => 1500, 'data_transacao' => "{$mes}-15", 'descricao' => 'Despesa da sala', 'conciliado' => true]);
    }

    // --- Página ------------------------------------------------------------------------------------------------------

    public function test_a_pagina_exige_permissao(): void
    {
        $this->actingAs($this->como($this->leitor))->get(RentabilidadeRealizada::getUrl())->assertForbidden();
        $this->actingAs($this->como($this->financeiro))->get(RentabilidadeRealizada::getUrl())->assertOk();
    }

    public function test_abre_no_ultimo_mes_completo_e_mostra_o_resultado_e_o_criterio(): void
    {
        $this->mesNegativo('2026-09');

        Livewire::actingAs($this->como($this->financeiro))
            ->test(RentabilidadeRealizada::class)
            ->assertSet('mes', '2026-09')
            ->assertSee('Turma A')
            ->assertSee('-500,00')
            ->assertSee('-50,0%')
            ->assertSee('por aluno</strong>', false);
    }

    public function test_avisa_da_saida_sem_conta_do_plano_e_do_docente_sem_aula(): void
    {
        $this->mesNegativo('2026-09');
        TransacaoBancaria::create(['banco_id' => $this->banco->id, 'tipo' => 'saida', 'valor' => 300, 'data_transacao' => '2026-09-20', 'descricao' => 'Sem conta', 'conciliado' => true]);

        Livewire::actingAs($this->como($this->financeiro))
            ->test(RentabilidadeRealizada::class)
            ->assertSee('1 saída(s) bancária(s), somando R$ 300,00')
            ->assertSee('estão sem conta do plano');
    }

    public function test_troca_de_criterio_e_de_agrupamento(): void
    {
        $this->mesNegativo('2026-09');

        Livewire::actingAs($this->como($this->financeiro))
            ->test(RentabilidadeRealizada::class)
            ->set('criterio', 'hora_aula')
            ->assertSee('por hora-aula (aulas no mês)</strong>', false)
            ->set('visao', 'curso')
            ->assertSee('Ensino Fundamental')
            ->assertSee('1 turma(s)')
            ->set('visao', 'unidade')
            ->assertSee('Unidade Central');
    }

    public function test_valores_invalidos_na_url_voltam_ao_padrao(): void
    {
        Livewire::actingAs($this->como($this->financeiro))
            ->withQueryParams(['mes' => 'lixo', 'criterio' => 'x', 'visao' => 'y'])
            ->test(RentabilidadeRealizada::class)
            ->assertSet('mes', '2026-09')
            ->assertSet('criterio', 'aluno')
            ->assertSet('visao', 'turma');
    }

    public function test_mes_sem_turmas_em_andamento_avisa(): void
    {
        Livewire::actingAs($this->como($this->financeiro))
            ->test(RentabilidadeRealizada::class)
            ->set('mes', '2024-03')
            ->assertSee('Nenhuma turma em andamento neste mês.');
    }

    // --- Centro de custo ---------------------------------------------------------------------------------------------

    public function test_centro_de_custo_guarda_o_vinculo_com_turma_curso_e_fora_do_resultado(): void
    {
        $administracao = CentroCusto::create(['nome' => 'Administração']);

        Livewire::actingAs($this->como($this->financeiro))
            ->test(EditCentroCusto::class, ['record' => $administracao->getKey()])
            ->fillForm(['nome' => 'Administração', 'curso_id' => $this->curso->id, 'fora_do_resultado_das_turmas' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $administracao->refresh();
        $this->assertSame($this->curso->id, $administracao->curso_id);
        $this->assertTrue($administracao->fora_do_resultado_das_turmas);
    }

    public function test_lista_de_centros_de_custo_mostra_como_cada_um_entra_no_resultado(): void
    {
        CentroCusto::create(['nome' => 'Folha', 'fora_do_resultado_das_turmas' => true]);
        CentroCusto::create(['nome' => 'Administração']);

        Livewire::actingAs($this->como($this->financeiro))
            ->test(ListCentroCustos::class)
            ->assertSee('Direto: Turma A')
            ->assertSee('Fora do resultado')
            ->assertSee('Indireto (rateado)');
    }

    // --- Minha Mesa e relatórios -------------------------------------------------------------------------------------

    public function test_fila_da_mesa_lista_a_turma_negativa_nos_ultimos_meses_e_marca_a_margem_critica(): void
    {
        foreach (['2026-07', '2026-08', '2026-09'] as $mes) {
            $this->mesNegativo($mes);
        }

        $fila = new TurmasComResultadoNegativo;

        $this->assertSame('View:RentabilidadeRealizada', $fila->permissao());
        $this->assertSame(1, $fila->contar($this->financeiro));
        $this->assertSame(1, $fila->atrasados($this->financeiro), 'margem de -50% passa da margem crítica de -20%');

        $item = $fila->itens($this->financeiro, 5)[0];
        $this->assertSame('Turma A', $item->titulo);
        $this->assertStringContainsString('Negativa há 3 mês(es)', $item->detalhe);
        $this->assertTrue($item->atrasado);
    }

    public function test_fila_da_mesa_fica_vazia_se_algum_dos_meses_foi_positivo(): void
    {
        $this->mesNegativo('2026-08');
        $this->mesNegativo('2026-09');
        // Em julho a turma rendeu mais do que custou.
        $fatura = Fatura::create(['contrato_id' => $this->contrato->id, 'vencimento' => '2026-07-10', 'status' => 'pago']);
        ItemFatura::create(['fatura_id' => $fatura->id, 'descricao' => 'Mensalidade', 'quantidade' => 1, 'valor_unitario' => 3000, 'desconto' => 0, 'tipo_desconto' => 'absoluto']);

        $fila = new TurmasComResultadoNegativo;

        $this->assertSame(0, $fila->contar($this->financeiro));
        $this->assertSame([], $fila->itens($this->financeiro, 5));
    }

    public function test_margem_negativa_leve_nao_e_atrasada(): void
    {
        foreach (['2026-07', '2026-08', '2026-09'] as $mes) {
            $this->mesNegativo($mes);
        }

        config(['financeiro.rentabilidade.margem_critica' => -80.0]);

        $fila = new TurmasComResultadoNegativo;

        $this->assertSame(1, $fila->contar($this->financeiro));
        $this->assertSame(0, $fila->atrasados($this->financeiro));
    }

    public function test_fonte_de_relatorio_entrega_as_turmas_do_mes_escolhido(): void
    {
        $this->mesNegativo('2026-09');

        $fonte = new RentabilidadeTurmas;
        $linhas = $fonte->linhas(['mes' => '2026-09', 'criterio' => 'turma'], 100);

        $this->assertSame('View:RentabilidadeRealizada', $fonte->permissao());
        $this->assertCount(1, $linhas);
        $this->assertSame('Turma A', $linhas[0]['turma']);
        $this->assertEquals(1000.0, $linhas[0]['faturado']);
        $this->assertEquals(1500.0, $linhas[0]['custo_direto']);
        $this->assertEquals(-500.0, $linhas[0]['resultado']);
        $this->assertEquals(-50.0, $linhas[0]['margem']);
        $this->assertSame([], $fonte->linhas(['mes' => '2024-01'], 100));
        $this->assertArrayHasKey('2026-09', $fonte->filtros()['mes']->opcoes);
    }
}

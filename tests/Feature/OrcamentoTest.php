<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatusOrcamento;
use App\Filament\Pages\OrcadoRealizado;
use App\Filament\Resources\Orcamentos\Pages\CreateOrcamento;
use App\Filament\Resources\Orcamentos\Pages\ListOrcamentos;
use App\Filament\Resources\Orcamentos\Pages\PlanilhaOrcamento;
use App\Filament\Resources\Orcamentos\Pages\ViewOrcamento;
use App\Models\Banco;
use App\Models\CentroCusto;
use App\Models\ContaPagar;
use App\Models\Orcamento;
use App\Models\PlanoConta;
use App\Models\TransacaoBancaria;
use App\Models\User;
use App\Services\OrcamentoService;
use App\Services\Pendencias\Fontes\OrcamentoEstourado;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Orçamento anual × realizado (Onda 43): criar, preencher, importar, aprovar e revisar; o comparativo com o realizado do DRE;
 * o saldo que a aprovação de compras consulta; a fila da Mesa e as telas.
 *
 * "Hoje" é segunda-feira, 26/10/2026.
 */
class OrcamentoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $leitor;

    private PlanoConta $mensalidades;

    private PlanoConta $despesas;

    private PlanoConta $material;

    private PlanoConta $limpeza;

    private CentroCusto $secretaria;

    private Banco $banco;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-26 10:00:00'));
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['super_admin', 'admin', 'secretaria'] as $papel) {
            Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        }

        foreach (['ViewAny:Orcamento', 'View:Orcamento', 'Create:Orcamento', 'Update:Orcamento', 'Delete:Orcamento', 'Aprovar:Orcamento', 'View:OrcadoRealizado'] as $nome) {
            Role::findByName('admin')->givePermissionTo(Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']));
        }

        foreach (['ViewAny:Orcamento', 'View:Orcamento', 'View:OrcadoRealizado'] as $nome) {
            Role::findByName('secretaria')->givePermissionTo(Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']));
        }

        $this->admin = $this->usuario('admin');
        $this->leitor = $this->usuario('secretaria');

        $this->mensalidades = PlanoConta::create(['codigo' => '1', 'nome' => 'Mensalidades', 'tipo' => 'receita']);
        $this->despesas = PlanoConta::create(['codigo' => '2', 'nome' => 'Despesas administrativas', 'tipo' => 'despesa']);
        $this->material = PlanoConta::create(['codigo' => '2.1', 'nome' => 'Material de escritório', 'tipo' => 'despesa', 'pai_id' => $this->despesas->id]);
        $this->limpeza = PlanoConta::create(['codigo' => '2.2', 'nome' => 'Limpeza', 'tipo' => 'despesa', 'pai_id' => $this->despesas->id]);
        $this->secretaria = CentroCusto::create(['nome' => 'Secretaria', 'is_active' => true]);
        $this->banco = Banco::create(['nome' => 'Banco', 'is_active' => true]);
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

    private function servico(): OrcamentoService
    {
        return app(OrcamentoService::class);
    }

    private function movimento(string $tipo, float $valor, string $data, ?PlanoConta $plano, ?CentroCusto $centro = null): TransacaoBancaria
    {
        return TransacaoBancaria::create([
            'banco_id' => $this->banco->id,
            'plano_conta_id' => $plano?->id,
            'centro_custo_id' => $centro?->id,
            'tipo' => $tipo,
            'valor' => $valor,
            'data_transacao' => $data,
            'descricao' => 'Movimento',
            'conciliado' => true,
        ]);
    }

    /**
     * Orçamento 2026 aprovado: mensalidades 10.000/mês e despesas administrativas (mãe) 1.000/mês.
     */
    private function orcamentoAprovado(): Orcamento
    {
        $orcamento = $this->servico()->criar(2026, 'Orçamento 2026', null, 0, $this->admin);

        for ($mes = 1; $mes <= 12; $mes++) {
            $this->servico()->definirValor($orcamento, $this->mensalidades->id, null, $mes, 10000);
            $this->servico()->definirValor($orcamento, $this->despesas->id, null, $mes, 1000);
        }

        return $this->servico()->aprovar($orcamento, $this->admin);
    }

    // --- Criar e preencher -------------------------------------------------------------------------------------------

    public function test_criar_em_branco_numera_as_versoes_do_ano(): void
    {
        $primeiro = $this->servico()->criar(2027, 'Orçamento 2027', null, 0, $this->admin);
        $segundo = $this->servico()->criar(2027, 'Outro', null, 0, $this->admin);

        $this->assertSame(1, $primeiro->versao);
        $this->assertSame(2, $segundo->versao);
        $this->assertSame(StatusOrcamento::Rascunho, $primeiro->status);
        $this->assertSame(0, $primeiro->linhas()->count());
    }

    public function test_copiar_aplica_o_reajuste_e_preserva_a_origem(): void
    {
        $base = $this->orcamentoAprovado();

        $copia = $this->servico()->criar(2027, 'Orçamento 2027', $base, 5, $this->admin);

        $this->assertSame($base->id, $copia->origem_orcamento_id);
        $this->assertSame(24, $copia->linhas()->count());
        $this->assertEquals(10500.0, (float) $copia->linhas()->where('plano_conta_id', $this->mensalidades->id)->where('mes', 3)->value('valor_previsto'));
        $this->assertEquals(10000.0, (float) $base->linhas()->where('plano_conta_id', $this->mensalidades->id)->where('mes', 3)->value('valor_previsto'), 'o original não muda');
    }

    public function test_definir_valor_valida_e_zero_apaga_a_linha(): void
    {
        $orcamento = $this->servico()->criar(2026, 'Teste', null, 0, $this->admin);
        $ativo = PlanoConta::create(['codigo' => '9', 'nome' => 'Caixa', 'tipo' => 'ativo']);

        $this->servico()->definirValor($orcamento, $this->despesas->id, null, 1, 100);
        $this->servico()->definirValor($orcamento, $this->despesas->id, null, 1, 250.5);
        $this->assertSame(1, $orcamento->linhas()->count(), 'atualiza em vez de duplicar');
        $this->assertEquals(250.5, (float) $orcamento->linhas()->value('valor_previsto'));

        $this->servico()->definirValor($orcamento, $this->despesas->id, null, 1, 0);
        $this->assertSame(0, $orcamento->linhas()->count());

        foreach ([[$this->despesas->id, 13, 10, 'mês'], [$this->despesas->id, 1, -5, 'negativo'], [$ativo->id, 1, 10, 'receita e de despesa']] as [$plano, $mes, $valor, $trecho]) {
            try {
                $this->servico()->definirValor($orcamento, $plano, null, $mes, $valor);
                $this->fail('devia recusar');
            } catch (\DomainException $e) {
                $this->assertStringContainsString($trecho, $e->getMessage());
            }
        }
    }

    public function test_importar_csv_aceita_formato_brasileiro_e_lista_os_erros(): void
    {
        $orcamento = $this->servico()->criar(2026, 'Teste', null, 0, $this->admin);
        $csv = "plano;centro;jan;fev;mar;abr;mai;jun;jul;ago;set;out;nov;dez\n"
            ."2.1;Secretaria;1.200,50;1.000;;;;;;;;;;300\n"
            ."99;;100;;;;;;;;;;;\n"
            ."2.2;Inexistente;100;;;;;;;;;;;\n";

        $resultado = $this->servico()->importarCsv($orcamento, $csv);

        $this->assertSame(1, $resultado['linhas']);
        $this->assertCount(2, $resultado['erros']);
        $this->assertStringContainsString('não existe conta do plano com o código "99"', $resultado['erros'][0]);
        $this->assertStringContainsString('não existe centro de custo "Inexistente"', $resultado['erros'][1]);

        $janeiro = $orcamento->linhas()->where('plano_conta_id', $this->material->id)->where('mes', 1)->first();
        $this->assertEquals(1200.5, (float) $janeiro->valor_previsto);
        $this->assertSame($this->secretaria->id, $janeiro->centro_custo_id);
        $this->assertEquals(1000.0, (float) $orcamento->linhas()->where('mes', 2)->value('valor_previsto'));
        $this->assertEquals(300.0, (float) $orcamento->linhas()->where('mes', 12)->value('valor_previsto'));
        $this->assertSame(3, $orcamento->linhas()->count());
    }

    // --- Aprovar e revisar -------------------------------------------------------------------------------------------

    public function test_aprovar_exige_valores_e_substitui_o_aprovado_anterior_do_ano(): void
    {
        $vazio = $this->servico()->criar(2026, 'Vazio', null, 0, $this->admin);

        try {
            $this->servico()->aprovar($vazio, $this->admin);
            $this->fail('orçamento em branco não se aprova');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('em branco', $e->getMessage());
        }

        $this->servico()->excluir($vazio);
        $v1 = $this->orcamentoAprovado();
        $this->assertSame($v1->id, Orcamento::vigenteDoAno(2026)->id);
        $this->assertSame($this->admin->id, $v1->aprovado_por_user_id);

        $v2 = $this->servico()->revisar($v1, $this->admin);
        $this->servico()->definirValor($v2, $this->despesas->id, null, 11, 5000);
        $this->servico()->aprovar($v2, $this->admin);

        $this->assertSame(StatusOrcamento::Substituido, $v1->refresh()->status);
        $this->assertSame($v2->id, Orcamento::vigenteDoAno(2026)->id);
        $this->assertSame(1, Orcamento::query()->where('ano', 2026)->aprovados()->count());
    }

    public function test_o_aprovado_nao_muda_e_so_ele_pode_ser_revisado(): void
    {
        $aprovado = $this->orcamentoAprovado();

        foreach ([fn () => $this->servico()->definirValor($aprovado, $this->despesas->id, null, 1, 1), fn () => $this->servico()->importarCsv($aprovado, '2.1;;1'), fn () => $this->servico()->excluir($aprovado)] as $tentativa) {
            try {
                $tentativa();
                $this->fail('o aprovado não pode mudar');
            } catch (\DomainException $e) {
                $this->assertStringContainsString('não muda', $e->getMessage());
            }
        }

        $revisao = $this->servico()->revisar($aprovado, $this->admin);
        $this->assertSame(2, $revisao->versao);
        $this->assertSame($aprovado->id, $revisao->origem_orcamento_id);
        $this->assertSame(24, $revisao->linhas()->count());

        try {
            $this->servico()->revisar($aprovado, $this->admin);
            $this->fail('já há um rascunho aberto');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Já existe um rascunho', $e->getMessage());
        }

        try {
            $this->servico()->revisar($revisao, $this->admin);
            $this->fail('rascunho não se revisa');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Só um orçamento aprovado', $e->getMessage());
        }

        $this->servico()->excluir($revisao);
        $this->assertNull(Orcamento::query()->find($revisao->id));
    }

    // --- Orçado × realizado ------------------------------------------------------------------------------------------

    public function test_comparativo_soma_o_realizado_da_conta_e_das_subcontas_ate_o_mes(): void
    {
        $orcamento = $this->orcamentoAprovado();
        $this->movimento('saida', 400, '2026-01-10', $this->material);
        $this->movimento('saida', 700, '2026-02-15', $this->limpeza);
        $this->movimento('saida', 250, '2026-03-05', $this->despesas);
        $this->movimento('saida', 9999, '2026-04-02', $this->material, null);
        $this->movimento('entrada', 9000, '2026-01-20', $this->mensalidades);
        $this->movimento('saida', 111, '2026-02-02', null);

        $linhas = collect($this->servico()->comparativo($orcamento, 3))->keyBy(fn (array $l): string => $l['plano']->codigo);

        $this->assertEquals(3000.0, $linhas['2']['previsto'], 'janeiro a março');
        $this->assertEquals(1350.0, $linhas['2']['realizado'], '400 + 700 + 250: a mãe e as filhas, sem abril');
        $this->assertEquals(-1650.0, $linhas['2']['variacao']);
        $this->assertSame(45.0, $linhas['2']['percentual']);
        $this->assertSame('ok', $linhas['2']['situacao']);
        $this->assertEquals(9000.0, $linhas['1']['realizado']);
        $this->assertSame('estourado', $linhas['1']['situacao'], 'receita em 30% do previsto de 30.000 está muito abaixo do esperado');
    }

    public function test_semaforo_de_despesa_e_de_receita(): void
    {
        $s = $this->servico();

        $this->assertSame('ok', $s->situacao('despesa', 1000, 800));
        $this->assertSame('atencao', $s->situacao('despesa', 1000, 900));
        $this->assertSame('atencao', $s->situacao('despesa', 1000, 1000));
        $this->assertSame('estourado', $s->situacao('despesa', 1000, 1001));
        $this->assertSame('estourado', $s->situacao('despesa', 0, 10), 'gastou sem previsão');
        $this->assertSame('ok', $s->situacao('despesa', 0, 0));

        $this->assertSame('ok', $s->situacao('receita', 1000, 1000));
        $this->assertSame('atencao', $s->situacao('receita', 1000, 950));
        $this->assertSame('estourado', $s->situacao('receita', 1000, 500));
    }

    public function test_comparativo_respeita_o_centro_de_custo_da_linha(): void
    {
        $orcamento = $this->servico()->criar(2026, 'Por centro', null, 0, $this->admin);
        $this->servico()->definirValor($orcamento, $this->material->id, $this->secretaria->id, 1, 500);
        $this->servico()->aprovar($orcamento, $this->admin);
        $outro = CentroCusto::create(['nome' => 'Direção', 'is_active' => true]);

        $this->movimento('saida', 300, '2026-01-10', $this->material, $this->secretaria);
        $this->movimento('saida', 999, '2026-01-11', $this->material, $outro);

        $this->assertEquals(300.0, $this->servico()->comparativo($orcamento, 1)[0]['realizado']);
    }

    public function test_sem_classificacao_conta_o_movimento_que_ficou_de_fora(): void
    {
        $this->movimento('saida', 111, '2026-02-02', null);
        $this->movimento('entrada', 50, '2026-03-02', null);
        $this->movimento('saida', 70, '2026-08-02', null);

        $this->assertSame(['quantidade' => 2, 'valor' => 161.0], $this->servico()->semClassificacao(2026, 3));
    }

    // --- Saldo para compras ------------------------------------------------------------------------------------------

    public function test_saldo_disponivel_usa_a_conta_ou_a_mae_e_desconta_o_comprometido(): void
    {
        $this->orcamentoAprovado();
        $this->movimento('saida', 2000, '2026-05-10', $this->limpeza);
        ContaPagar::factory()->create(['valor' => 1500, 'vencimento' => '2026-11-10', 'status' => 'pendente', 'plano_conta_id' => $this->material->id]);
        ContaPagar::factory()->create(['valor' => 700, 'vencimento' => '2026-11-12', 'status' => 'pago', 'plano_conta_id' => $this->material->id]);

        $saldo = $this->servico()->saldoDisponivel($this->material->id, null, now(), 300);

        $this->assertNotNull($saldo);
        $this->assertStringContainsString('Despesas administrativas', $saldo['plano_orcado']);
        $this->assertEquals(12000.0, $saldo['previsto']);
        $this->assertEquals(2000.0, $saldo['realizado']);
        $this->assertEquals(1800.0, $saldo['comprometido'], '1.500 em aberto + 300 informados; a paga não conta');
        $this->assertEquals(8200.0, $saldo['saldo']);
    }

    public function test_saldo_disponivel_e_nulo_sem_orcamento_aprovado_ou_sem_linha(): void
    {
        $this->assertNull($this->servico()->saldoDisponivel($this->material->id, null, now()));

        $this->orcamentoAprovado();
        $outra = PlanoConta::create(['codigo' => '3', 'nome' => 'Sem previsão', 'tipo' => 'despesa']);

        $this->assertNull($this->servico()->saldoDisponivel($outra->id, null, now()));
        $this->assertNull($this->servico()->saldoDisponivel(null, null, now()));
        $this->assertNull($this->servico()->saldoDisponivel($this->material->id, null, Carbon::parse('2027-02-01')), 'outro ano, sem orçamento aprovado');
    }

    // --- Mesa --------------------------------------------------------------------------------------------------------

    public function test_a_fila_da_mesa_lista_despesa_em_atencao_ou_estourada_no_ano_ate_o_mes(): void
    {
        Cache::flush();
        $this->orcamentoAprovado();
        // Out/2026: previsto acumulado de despesas = 10.000. 9.500 = 95% (atenção). Depois 600 = 101% (estouro).
        $this->movimento('saida', 9500, '2026-06-10', $this->material);

        $fila = new OrcamentoEstourado;
        $this->assertSame('View:OrcadoRealizado', $fila->permissao());
        $this->assertSame(1, $fila->contar($this->admin));
        $this->assertSame(0, $fila->atrasados($this->admin), 'atenção ainda não é atraso');

        $this->movimento('saida', 600, '2026-07-10', $this->limpeza);
        $fila->esquecer();

        $this->assertSame(1, $fila->atrasados($this->admin));
        $this->assertStringContainsString('Despesas administrativas', $fila->itens($this->admin, 3)[0]->titulo);
        $this->assertTrue($fila->itens($this->admin, 3)[0]->atrasado);
    }

    // --- Telas -------------------------------------------------------------------------------------------------------

    public function test_criar_pela_tela_copia_o_orcamento_e_leva_para_a_planilha(): void
    {
        $base = $this->orcamentoAprovado();

        Livewire::actingAs($this->como($this->admin))
            ->test(CreateOrcamento::class)
            ->fillForm(['ano' => 2027, 'nome' => 'Orçamento 2027', 'base_id' => $base->id, 'reajuste' => 10])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $novo = Orcamento::query()->where('ano', 2027)->firstOrFail();
        $this->assertSame(24, $novo->linhas()->count());
        $this->assertEquals(11000.0, (float) $novo->linhas()->where('plano_conta_id', $this->mensalidades->id)->where('mes', 1)->value('valor_previsto'));
    }

    public function test_acoes_da_tela_seguem_o_estado_e_a_permissao(): void
    {
        $rascunho = $this->servico()->criar(2027, 'Rascunho', null, 0, $this->admin);
        $this->servico()->definirValor($rascunho, $this->despesas->id, null, 1, 100);
        $aprovado = $this->orcamentoAprovado();

        $tela = fn (Orcamento $o, User $u) => Livewire::actingAs($this->como($u))->test(ViewOrcamento::class, ['record' => $o->getKey()]);

        $tela($rascunho, $this->admin)->assertActionVisible('aprovar')->assertActionHidden('revisar')->assertActionVisible('excluir')->assertActionVisible('importar');
        $tela($aprovado, $this->admin)->assertActionHidden('aprovar')->assertActionVisible('revisar')->assertActionHidden('excluir')->assertActionHidden('importar');
        $tela($rascunho, $this->leitor)->assertActionHidden('aprovar')->assertActionHidden('excluir')->assertActionHidden('importar');

        $tela($rascunho, $this->admin)->callAction('aprovar')->assertNotified('Orçamento aprovado');
        $this->assertTrue($rascunho->refresh()->estaAprovado());

        $tela($aprovado->refresh(), $this->admin)->callAction('revisar')->assertRedirect();
        $this->assertSame(1, Orcamento::query()->where('ano', 2026)->where('status', 'rascunho')->count());
    }

    public function test_aprovar_um_orcamento_em_branco_avisa_na_tela(): void
    {
        $vazio = $this->servico()->criar(2027, 'Vazio', null, 0, $this->admin);

        Livewire::actingAs($this->como($this->admin))
            ->test(ViewOrcamento::class, ['record' => $vazio->getKey()])
            ->callAction('aprovar')
            ->assertNotified('Não foi possível concluir');

        $this->assertTrue($vazio->refresh()->ehRascunho());
    }

    public function test_planilha_adiciona_salva_e_remove_linhas(): void
    {
        $orcamento = $this->servico()->criar(2026, 'Planilha', null, 0, $this->admin);

        $tela = Livewire::actingAs($this->como($this->admin))->test(PlanilhaOrcamento::class, ['record' => $orcamento->getKey()]);

        $tela->set('novoPlano', $this->material->id)
            ->set('novoCentro', $this->secretaria->id)
            ->call('adicionarLinha')
            ->set('grade.'.$this->material->id.':'.$this->secretaria->id.'.1', '1.250,75')
            ->set('grade.'.$this->material->id.':'.$this->secretaria->id.'.2', '300')
            ->call('salvar')
            ->assertNotified('Planilha salva');

        $this->assertEquals(1250.75, (float) $orcamento->linhas()->where('mes', 1)->value('valor_previsto'));
        $this->assertSame(2, $orcamento->linhas()->count());

        $tela->call('removerLinha', $this->material->id.':'.$this->secretaria->id)->call('salvar');

        $this->assertSame(0, $orcamento->linhas()->count());
    }

    public function test_planilha_recusa_valor_invalido_e_nao_edita_o_aprovado(): void
    {
        $orcamento = $this->servico()->criar(2026, 'Planilha', null, 0, $this->admin);
        $chave = $this->material->id.':0';

        Livewire::actingAs($this->como($this->admin))
            ->test(PlanilhaOrcamento::class, ['record' => $orcamento->getKey()])
            ->set('novoPlano', $this->material->id)
            ->call('adicionarLinha')
            ->set("grade.{$chave}.1", 'abc')
            ->call('salvar')
            ->assertNotified('Não foi possível salvar');

        $this->assertSame(0, $orcamento->linhas()->count());

        $aprovado = $this->orcamentoAprovado();

        Livewire::actingAs($this->como($this->admin))
            ->test(PlanilhaOrcamento::class, ['record' => $aprovado->getKey()])
            ->assertSee('Planilha')
            ->call('salvar')
            ->assertForbidden();
    }

    public function test_orcado_realizado_so_para_quem_pode_e_mostra_semaforo_e_aviso(): void
    {
        $this->orcamentoAprovado();
        $this->movimento('saida', 12500, '2026-03-10', $this->material);
        $this->movimento('saida', 80, '2026-03-11', null);

        $this->actingAs($this->como($this->admin));
        $this->assertTrue(OrcadoRealizado::canAccess());

        $usuarioSemPermissao = $this->usuario('secretaria');
        Role::findByName('secretaria')->revokePermissionTo('View:OrcadoRealizado');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->actingAs($this->como(User::query()->findOrFail($usuarioSemPermissao->id)));
        $this->assertFalse(OrcadoRealizado::canAccess());

        Livewire::actingAs($this->como($this->admin))
            ->test(OrcadoRealizado::class)
            ->assertSuccessful()
            ->set('ateMes', 3)
            ->assertSee('Despesas administrativas')
            ->assertSee('Fora do previsto')
            ->assertSee('sem conta do plano');
    }

    public function test_lista_de_orcamentos_abre_para_quem_pode_ver(): void
    {
        $this->orcamentoAprovado();

        Livewire::actingAs($this->como($this->leitor))->test(ListOrcamentos::class)->assertSuccessful()->assertSee('Orçamento 2026');
    }
}

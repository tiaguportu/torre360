<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DestinoItemCompra;
use App\Enums\StatusContaPagar;
use App\Enums\StatusRequisicaoCompra;
use App\Models\BemPatrimonial;
use App\Models\CentroCusto;
use App\Models\ContaPagar;
use App\Models\Cotacao;
use App\Models\Fornecedor;
use App\Models\ItemEstoque;
use App\Models\PedidoCompra;
use App\Models\PlanoConta;
use App\Models\RecebimentoCompra;
use App\Models\RequisicaoCompra;
use App\Models\User;
use App\Services\ComprasService;
use App\Services\OrcamentoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

/**
 * Compras e cotações (Onda 38), regras do serviço: requisição, cotação, escolha, alçada, orçamento, segregação de funções e recebimento
 * (estoque, patrimônio e conta a pagar).
 *
 * "Hoje" é segunda-feira, 26/10/2026.
 */
class ComprasServiceTest extends TestCase
{
    use ProibeLazyLoading;
    use RefreshDatabase;

    private User $solicitante;

    private User $coordenador;

    private User $diretor;

    private User $almoxarife;

    private Fornecedor $papelaria;

    private Fornecedor $distribuidora;

    private Fornecedor $atacado;

    private PlanoConta $material;

    private CentroCusto $secretaria;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-26 10:00:00'));
        Storage::fake('local');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['solicitante', 'coordenador', 'diretor', 'almoxarife'] as $papel) {
            Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        }

        Role::findByName('coordenador')->givePermissionTo(Permission::firstOrCreate(['name' => 'AprovarNivel1:PedidoCompra', 'guard_name' => 'web']));
        Role::findByName('diretor')->givePermissionTo(Permission::firstOrCreate(['name' => 'AprovarNivel2:PedidoCompra', 'guard_name' => 'web']));
        Role::findByName('almoxarife')->givePermissionTo(Permission::firstOrCreate(['name' => 'Receber:PedidoCompra', 'guard_name' => 'web']));

        $this->solicitante = $this->usuario('solicitante', 'Ana Solicitante');
        $this->coordenador = $this->usuario('coordenador', 'Carla Coordenadora');
        $this->diretor = $this->usuario('diretor', 'Davi Diretor');
        $this->almoxarife = $this->usuario('almoxarife', 'Alex Almoxarife');

        $this->papelaria = Fornecedor::create(['razao_social' => 'Papelaria Central']);
        $this->distribuidora = Fornecedor::create(['razao_social' => 'Distribuidora Norte']);
        $this->atacado = Fornecedor::create(['razao_social' => 'Atacado Sul']);
        $this->material = PlanoConta::create(['codigo' => '2.1', 'nome' => 'Material de escritório', 'tipo' => 'despesa']);
        $this->secretaria = CentroCusto::create(['nome' => 'Secretaria', 'is_active' => true]);
    }

    private function usuario(string $papel, string $nome): User
    {
        $usuario = User::factory()->create(['name' => $nome, 'activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $usuario->assignRole($papel);

        return $usuario;
    }

    private function servico(): ComprasService
    {
        return app(ComprasService::class);
    }

    /**
     * @param  list<array<string, mixed>>|null  $itens
     */
    private function requisicao(?array $itens = null, array $dados = []): RequisicaoCompra
    {
        return $this->servico()->criarRequisicao($this->solicitante, $dados + [
            'titulo' => 'Papel para as provas',
            'justificativa' => 'Acabou o estoque.',
            'centro_custo_id' => $this->secretaria->id,
            'plano_conta_id' => $this->material->id,
        ], $itens ?? [['descricao' => 'Resma de papel A4', 'quantidade' => 10, 'unidade_medida' => 'rs', 'estimativa_unitaria' => 25]]);
    }

    private function emCotacao(?array $itens = null, array $dados = []): RequisicaoCompra
    {
        return $this->servico()->enviarParaCotacao($this->requisicao($itens, $dados), $this->solicitante);
    }

    /**
     * Cota todos os itens da requisição com o mesmo preço unitário.
     */
    private function cotar(RequisicaoCompra $requisicao, Fornecedor $fornecedor, float $preco, array $dados = []): Cotacao
    {
        $precos = $requisicao->itens()->pluck('id')->mapWithKeys(fn (int $id): array => [$id => $preco])->all();

        return $this->servico()->registrarCotacao($requisicao, $fornecedor, $precos, $dados + ['prazo_entrega_dias' => 5, 'prazo_pagamento_dias' => 30], $this->coordenador);
    }

    private function pedidoAguardando(float $preco = 20.0): PedidoCompra
    {
        $requisicao = $this->emCotacao();
        $cotacao = $this->cotar($requisicao, $this->papelaria, $preco);

        return $this->servico()->escolherCotacao($requisicao, $cotacao, null, 'Fornecedor único da região.', $this->coordenador);
    }

    private function pedidoAprovado(float $preco = 20.0): PedidoCompra
    {
        $pedido = $this->pedidoAguardando($preco);

        return $this->servico()->aprovar($pedido->refresh(), $this->coordenador);
    }

    private function itemId(RequisicaoCompra $requisicao): int
    {
        return (int) $requisicao->itens()->value('id');
    }

    // --- Requisição --------------------------------------------------------------------------------------------------

    public function test_criar_requisicao_numera_e_guarda_os_itens_em_rascunho(): void
    {
        $primeira = $this->requisicao();
        $segunda = $this->requisicao();

        $this->assertSame('REQ-2026-0001', $primeira->numero);
        $this->assertSame('REQ-2026-0002', $segunda->numero);
        $this->assertSame(StatusRequisicaoCompra::Rascunho, $primeira->status);
        $this->assertSame($this->solicitante->id, $primeira->solicitante_user_id);
        $this->assertSame(1, $primeira->itens()->count());
        $this->assertEquals(250.0, $primeira->load('itens')->valorEstimado());
    }

    public function test_requisicao_recusa_dados_incompletos_e_item_de_estoque_sem_item(): void
    {
        $casos = [
            'sem título' => [['titulo' => ' '], null, 'título'],
            'sem justificativa' => [['justificativa' => ''], null, 'por que'],
            'sem itens' => [[], [], 'ao menos um item'],
            'item sem descrição' => [[], [['descricao' => '', 'quantidade' => 1]], 'descreva'],
            'quantidade zero' => [[], [['descricao' => 'X', 'quantidade' => 0]], 'maior que zero'],
            'estoque sem item' => [[], [['descricao' => 'X', 'quantidade' => 1, 'destino' => 'estoque']], 'item de estoque'],
            'bem fracionado' => [[], [['descricao' => 'Cadeira', 'quantidade' => 1.5, 'destino' => 'patrimonio']], 'unidades inteiras'],
        ];

        foreach ($casos as $nome => [$dados, $itens, $trecho]) {
            try {
                $this->requisicao($itens, $dados);
                $this->fail("{$nome}: devia recusar");
            } catch (\DomainException $e) {
                $this->assertStringContainsString($trecho, $e->getMessage(), $nome);
            }
        }
    }

    public function test_so_o_rascunho_se_edita_e_envia_uma_vez(): void
    {
        $requisicao = $this->requisicao();

        $atualizada = $this->servico()->atualizarRequisicao($requisicao, ['titulo' => 'Novo título', 'justificativa' => 'Outra razão'], [['descricao' => 'Caneta', 'quantidade' => 50]]);
        $this->assertSame('Novo título', $atualizada->titulo);
        $this->assertSame(['Caneta'], $atualizada->itens()->pluck('descricao')->all());

        $this->servico()->enviarParaCotacao($atualizada, $this->solicitante);

        foreach ([fn () => $this->servico()->atualizarRequisicao($atualizada->refresh(), ['titulo' => 'x', 'justificativa' => 'y'], [['descricao' => 'a', 'quantidade' => 1]]), fn () => $this->servico()->enviarParaCotacao($atualizada->refresh(), $this->solicitante)] as $tentativa) {
            try {
                $tentativa();
                $this->fail('já saiu do rascunho');
            } catch (\DomainException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    // --- Cotação -----------------------------------------------------------------------------------------------------

    public function test_cotacao_exige_preco_em_todos_os_itens_e_atualiza_a_do_mesmo_fornecedor(): void
    {
        $requisicao = $this->emCotacao([['descricao' => 'A', 'quantidade' => 2], ['descricao' => 'B', 'quantidade' => 1]]);
        [$a, $b] = $requisicao->itens()->pluck('id')->all();

        try {
            $this->servico()->registrarCotacao($requisicao, $this->papelaria, [$a => 10], []);
            $this->fail('cotação incompleta');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('cobrir todos os itens', $e->getMessage());
        }

        $this->servico()->registrarCotacao($requisicao, $this->papelaria, [$a => 10, $b => 5], ['frete' => 7]);
        $this->servico()->registrarCotacao($requisicao, $this->papelaria, [$a => 9, $b => 5], ['frete' => 7]);

        $this->assertSame(1, $requisicao->cotacoes()->count(), 'o mesmo fornecedor atualiza a própria cotação');
        $this->assertSame(2, $requisicao->cotacoes()->first()->itens()->count());
    }

    public function test_nao_se_cota_requisicao_fora_de_cotacao(): void
    {
        $rascunho = $this->requisicao();

        $this->expectException(\DomainException::class);
        $this->cotar($rascunho, $this->papelaria, 10);
    }

    public function test_mapa_comparativo_marca_a_mais_barata_o_menor_preco_por_item_e_o_fornecedor_com_divergencia(): void
    {
        $requisicao = $this->emCotacao();
        $cara = $this->cotar($requisicao, $this->papelaria, 22, ['frete' => 0]);
        $barata = $this->cotar($requisicao, $this->distribuidora, 20, ['frete' => 15]);

        $mapa = $this->servico()->mapaComparativo($requisicao);

        $totais = collect($mapa['cotacoes'])->pluck('total', 'id');
        $this->assertEquals(220.0, $totais[$cara->id]);
        $this->assertEquals(215.0, $totais[$barata->id]);
        $this->assertSame([$barata->id], collect($mapa['cotacoes'])->where('mais_barata', true)->pluck('id')->all());
        $this->assertEquals(20.0, $mapa['itens'][0]['menor']);
        $this->assertSame(1, $mapa['minimo'], 'até R$ 500, uma cotação basta');

        // O fornecedor que entregou com divergência vem sinalizado na próxima cotação.
        $pedido = $this->pedidoAprovadoDe($this->distribuidora);
        $this->servico()->receber($pedido, $this->almoxarife, [$this->itemId($pedido->requisicao) => 10], now(), 'NF 1', null, 'Nota veio com preço diferente do pedido.', true);

        $mapa = $this->servico()->mapaComparativo($requisicao->refresh());
        $this->assertSame(1, collect($mapa['cotacoes'])->firstWhere('id', $barata->id)['divergencias']);
        $this->assertSame(0, collect($mapa['cotacoes'])->firstWhere('id', $cara->id)['divergencias']);
    }

    private function pedidoAprovadoDe(Fornecedor $fornecedor): PedidoCompra
    {
        $requisicao = $this->emCotacao();
        $cotacao = $this->cotar($requisicao, $fornecedor, 20);
        $pedido = $this->servico()->escolherCotacao($requisicao, $cotacao, null, 'Fornecedor único.', $this->coordenador);

        return $this->servico()->aprovar($pedido->refresh(), $this->coordenador);
    }

    // --- Escolha -----------------------------------------------------------------------------------------------------

    public function test_escolher_cria_o_pedido_com_o_valor_e_a_alcada(): void
    {
        $requisicao = $this->emCotacao();
        $cotacao = $this->cotar($requisicao, $this->papelaria, 20, ['frete' => 10]);

        $pedido = $this->servico()->escolherCotacao($requisicao, $cotacao, null, 'Só este fornecedor entrega.', $this->coordenador);

        $this->assertSame('PC-2026-0001', $pedido->numero);
        $this->assertEquals(210.0, (float) $pedido->valor_total);
        $this->assertSame(1, $pedido->nivel_alcada, 'até R$ 1.000, nível 1');
        $this->assertTrue($cotacao->refresh()->escolhida);
        $this->assertSame(StatusRequisicaoCompra::AguardandoAprovacao, $requisicao->refresh()->status);
        $this->assertSame('Só este fornecedor entrega.', $requisicao->cotacao_dispensada_justificativa);
    }

    public function test_faixa_com_mais_de_uma_cotacao_exige_a_quantidade_ou_a_justificativa(): void
    {
        $requisicao = $this->emCotacao([['descricao' => 'Projetor', 'quantidade' => 1, 'estimativa_unitaria' => 2000]]);
        $unica = $this->cotar($requisicao, $this->papelaria, 2000);

        try {
            $this->servico()->escolherCotacao($requisicao, $unica, null, null, $this->coordenador);
            $this->fail('faltam cotações');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('pede 2 cotações e há 1', $e->getMessage());
        }

        $this->cotar($requisicao, $this->distribuidora, 2100);
        $pedido = $this->servico()->escolherCotacao($requisicao, $unica->refresh(), null, null, $this->coordenador);

        $this->assertSame(2, $pedido->nivel_alcada, 'R$ 2.000 passa da alçada de R$ 1.000');
        $this->assertNull($requisicao->refresh()->cotacao_dispensada_justificativa);
    }

    public function test_acima_de_cinco_mil_pedem_tres_cotacoes(): void
    {
        $this->assertSame(1, $this->servico()->minimoDeCotacoes(500));
        $this->assertSame(2, $this->servico()->minimoDeCotacoes(500.01));
        $this->assertSame(2, $this->servico()->minimoDeCotacoes(5000));
        $this->assertSame(3, $this->servico()->minimoDeCotacoes(5000.01));
        $this->assertSame(['nivel' => 3, 'permissao' => 'AprovarNivel3:PedidoCompra', 'nome' => 'Mantenedora'], $this->servico()->alcadaPara(10000.01));
    }

    public function test_escolher_a_que_nao_e_a_mais_barata_exige_o_motivo(): void
    {
        $requisicao = $this->emCotacao();
        $this->cotar($requisicao, $this->papelaria, 20);
        $cara = $this->cotar($requisicao, $this->distribuidora, 25);

        try {
            $this->servico()->escolherCotacao($requisicao, $cara, null, null, $this->coordenador);
            $this->fail('não é a mais barata');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('não é a de menor valor total', $e->getMessage());
        }

        $pedido = $this->servico()->escolherCotacao($requisicao, $cara, 'Entrega em 2 dias; a outra leva 15.', null, $this->coordenador);

        $this->assertSame('Entrega em 2 dias; a outra leva 15.', $cara->refresh()->motivo_escolha);
        $this->assertSame($cara->fornecedor_id, $pedido->fornecedor_id);
    }

    public function test_cotacao_com_validade_vencida_nao_vira_pedido(): void
    {
        $requisicao = $this->emCotacao();
        $vencida = $this->cotar($requisicao, $this->papelaria, 20, ['validade_ate' => '2026-10-20']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('validade');
        $this->servico()->escolherCotacao($requisicao, $vencida, null, 'x', $this->coordenador);
    }

    // --- Aprovação ---------------------------------------------------------------------------------------------------

    public function test_quem_solicita_nao_aprova_a_propria_compra_nem_o_super_admin_do_solicitante(): void
    {
        $pedido = $this->pedidoAguardando();
        $solicitanteComAlcada = $this->solicitante;
        $solicitanteComAlcada->assignRole('diretor');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->assertStringContainsString('Quem solicitou', (string) $this->servico()->impedimentoParaAprovar($pedido, $solicitanteComAlcada->refresh()));

        $this->expectException(\DomainException::class);
        $this->servico()->aprovar($pedido, $solicitanteComAlcada);
    }

    public function test_alcada_nivel_um_nao_aprova_valor_de_nivel_dois_mas_o_nivel_dois_aprova_ambos(): void
    {
        $requisicao = $this->emCotacao([['descricao' => 'Projetor', 'quantidade' => 1]]);
        $this->cotar($requisicao, $this->papelaria, 2500);
        $cotacao = $this->cotar($requisicao, $this->distribuidora, 2400);
        $pedido = $this->servico()->escolherCotacao($requisicao, $cotacao, null, null, $this->coordenador);

        $this->assertSame(2, $pedido->nivel_alcada);
        $this->assertStringContainsString('alçada de nível 2', (string) $this->servico()->impedimentoParaAprovar($pedido, $this->coordenador));
        $this->assertNull($this->servico()->impedimentoParaAprovar($pedido, $this->diretor));

        $aprovado = $this->servico()->aprovar($pedido, $this->diretor);
        $this->assertSame($this->diretor->id, $aprovado->aprovado_por_user_id);
        $this->assertNotNull($aprovado->aprovado_em);
        $this->assertSame(StatusRequisicaoCompra::Aprovada, $requisicao->refresh()->status);

        $pequeno = $this->pedidoAguardando(10);
        $this->assertNull($this->servico()->impedimentoParaAprovar($pequeno, $this->diretor), 'o nível 2 também aprova valor de nível 1');
    }

    public function test_pedido_so_se_aprova_uma_vez(): void
    {
        $pedido = $this->pedidoAprovado();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('não está aguardando aprovação');
        $this->servico()->aprovar($pedido->refresh(), $this->diretor);
    }

    public function test_compra_acima_do_saldo_do_orcamento_pede_justificativa_e_guarda_o_saldo(): void
    {
        $orcamento = app(OrcamentoService::class)->criar(2026, 'Orçamento 2026', null, 0, $this->diretor);
        app(OrcamentoService::class)->definirValor($orcamento, $this->material->id, null, 1, 150);
        app(OrcamentoService::class)->aprovar($orcamento, $this->diretor);

        $pedido = $this->pedidoAguardando(20);
        $saldo = $this->servico()->saldoOrcamentario($pedido);
        $this->assertNotNull($saldo);
        $this->assertEquals(150.0, $saldo['saldo']);
        $this->assertTrue($saldo['acima'], 'R$ 200 > R$ 150');

        try {
            $this->servico()->aprovar($pedido, $this->coordenador);
            $this->fail('acima do saldo sem justificativa');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Justifique a aprovação', $e->getMessage());
        }

        $aprovado = $this->servico()->aprovar($pedido->refresh(), $this->coordenador, 'Prova do vestibular não pode atrasar.');

        $this->assertSame('Prova do vestibular não pode atrasar.', $aprovado->justificativa_acima_do_orcamento);
        $this->assertEquals(150.0, $aprovado->saldo_orcamentario['saldo']);
    }

    public function test_configuracao_pode_bloquear_a_compra_acima_do_orcamento(): void
    {
        config(['compras.bloquear_acima_do_orcamento' => true]);
        $orcamento = app(OrcamentoService::class)->criar(2026, 'Orçamento 2026', null, 0, $this->diretor);
        app(OrcamentoService::class)->definirValor($orcamento, $this->material->id, null, 1, 100);
        app(OrcamentoService::class)->aprovar($orcamento, $this->diretor);

        $pedido = $this->pedidoAguardando(20);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Abra uma revisão do orçamento');
        $this->servico()->aprovar($pedido, $this->coordenador, 'Mesmo assim.');
    }

    public function test_pedido_aprovado_e_nao_recebido_conta_como_comprometido_do_orcamento(): void
    {
        $this->pedidoAprovado(20);

        $this->assertEquals(200.0, $this->servico()->comprometidoPorPedidos($this->material->id));
        $this->assertEquals(0.0, $this->servico()->comprometidoPorPedidos(null));
        $this->assertEquals(0.0, $this->servico()->comprometidoPorPedidos($this->material->id, PedidoCompra::query()->value('id')), 'o próprio pedido não conta contra ele');
    }

    public function test_recusar_encerra_ou_devolve_para_nova_cotacao(): void
    {
        $pedido = $this->pedidoAguardando();
        $requisicao = $pedido->requisicao;

        try {
            $this->servico()->recusar($pedido, $this->coordenador, ' ');
            $this->fail('motivo obrigatório');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('motivo', $e->getMessage());
        }

        $this->servico()->recusar($pedido, $this->coordenador, 'Preço fora do mercado.', true);
        $this->assertSame(StatusRequisicaoCompra::EmCotacao, $requisicao->refresh()->status);
        $this->assertSame(0, PedidoCompra::count());
        $this->assertSame(0, $requisicao->cotacoes()->where('escolhida', true)->count());
        $this->assertSame('Preço fora do mercado.', $requisicao->motivo_recusa);

        $novo = $this->servico()->escolherCotacao($requisicao, $requisicao->cotacoes()->first(), null, 'Segunda tentativa.', $this->coordenador);
        $this->servico()->recusar($novo, $this->coordenador, 'Sem verba neste trimestre.');
        $this->assertSame(StatusRequisicaoCompra::Recusada, $requisicao->refresh()->status);
    }

    // --- Recebimento -------------------------------------------------------------------------------------------------

    public function test_quem_aprovou_nao_recebe(): void
    {
        $pedido = $this->pedidoAprovado();

        $this->assertStringContainsString('Quem aprovou', (string) $this->servico()->impedimentoParaReceber($pedido, $this->coordenador));

        $this->expectException(\DomainException::class);
        $this->servico()->receber($pedido, $this->coordenador, [$this->itemId($pedido->requisicao) => 10], now(), 'NF 1');
    }

    public function test_desligar_a_segregacao_libera_quem_aprovou_a_receber(): void
    {
        config(['compras.segregar_funcoes' => false]);
        $pedido = $this->pedidoAprovado();

        $this->assertNull($this->servico()->impedimentoParaReceber($pedido, $this->coordenador));
    }

    public function test_recebimento_total_gera_a_conta_a_pagar_com_vencimento_e_classificacao(): void
    {
        $pedido = $this->pedidoAprovado(20);
        $itemId = $this->itemId($pedido->requisicao);

        $recebimento = $this->servico()->receber($pedido, $this->almoxarife, [$itemId => 10], now(), 'NF 4521', ['conteudo' => '%PDF-1.4 nota', 'extensao' => 'pdf']);

        $this->assertEquals(200.0, (float) $recebimento->valor_recebido);
        Storage::disk('local')->assertExists($recebimento->nota_fiscal_path);
        $this->assertSame(StatusRequisicaoCompra::Recebida, $pedido->requisicao->refresh()->status);

        $conta = ContaPagar::query()->where('pedido_compra_id', $pedido->id)->firstOrFail();
        $this->assertEquals(200.0, (float) $conta->valor);
        $this->assertSame('2026-11-25', $conta->vencimento->toDateString(), '30 dias depois da entrega');
        $this->assertSame(StatusContaPagar::Pendente, $conta->status);
        $this->assertSame($this->papelaria->id, $conta->fornecedor_id);
        $this->assertSame($this->material->id, $conta->plano_conta_id);
        $this->assertSame($this->secretaria->id, $conta->centro_custo_id);
        $this->assertSame($recebimento->id, $conta->recebimento_compra_id);
        $this->assertStringContainsString('NF 4521', $conta->descricao);
    }

    public function test_recebimento_parcial_gera_uma_conta_por_entrega_e_o_frete_entra_na_primeira(): void
    {
        $requisicao = $this->emCotacao();
        $cotacao = $this->cotar($requisicao, $this->papelaria, 20, ['frete' => 30]);
        $pedido = $this->servico()->aprovar($this->servico()->escolherCotacao($requisicao, $cotacao, null, 'Único.', $this->coordenador)->refresh(), $this->coordenador);
        $itemId = $this->itemId($requisicao);

        $primeiro = $this->servico()->receber($pedido, $this->almoxarife, [$itemId => 4], now(), 'NF 1');
        $this->assertEquals(110.0, (float) $primeiro->valor_recebido, '4 × 20 + frete 30');
        $this->assertSame(StatusRequisicaoCompra::RecebidaParcial, $requisicao->refresh()->status);
        $this->assertEquals(6.0, $requisicao->itens()->first()->quantidadePendente());

        try {
            $this->servico()->receber($pedido, $this->almoxarife, [$itemId => 7], now(), 'NF 2');
            $this->fail('mais do que o pendente');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('só faltam', $e->getMessage());
        }

        $segundo = $this->servico()->receber($pedido, $this->almoxarife, [$itemId => 6], now(), 'NF 2');
        $this->assertEquals(120.0, (float) $segundo->valor_recebido, 'sem frete na segunda entrega');
        $this->assertSame(StatusRequisicaoCompra::Recebida, $requisicao->refresh()->status);
        $this->assertSame(2, ContaPagar::query()->where('pedido_compra_id', $pedido->id)->count());
        $this->assertSame(2, RecebimentoCompra::count());
    }

    public function test_recebimento_exige_nota_quantidade_e_a_descricao_da_divergencia(): void
    {
        $pedido = $this->pedidoAprovado();
        $itemId = $this->itemId($pedido->requisicao);

        $casos = [
            'sem nota' => [fn () => $this->servico()->receber($pedido, $this->almoxarife, [$itemId => 1], now(), ' '), 'número da nota'],
            'sem quantidade' => [fn () => $this->servico()->receber($pedido, $this->almoxarife, [$itemId => 0], now(), 'NF 1'), 'quantidade recebida'],
            'divergência sem descrição' => [fn () => $this->servico()->receber($pedido, $this->almoxarife, [$itemId => 1], now(), 'NF 1', null, '', true), 'Descreva a divergência'],
            'arquivo inválido' => [fn () => $this->servico()->receber($pedido, $this->almoxarife, [$itemId => 1], now(), 'NF 1', ['conteudo' => '<html>', 'extensao' => 'html']), 'PDF, JPG ou PNG'],
        ];

        foreach ($casos as $nome => [$tentativa, $trecho]) {
            try {
                $tentativa();
                $this->fail("{$nome}: devia recusar");
            } catch (\DomainException $e) {
                $this->assertStringContainsString($trecho, $e->getMessage(), $nome);
            }
        }

        $this->assertSame(0, RecebimentoCompra::count());
        $this->assertSame(0, ContaPagar::count());
    }

    public function test_item_de_estoque_da_entrada_no_saldo_com_o_custo_da_compra(): void
    {
        $papel = ItemEstoque::create(['nome' => 'Papel A4', 'unidade_medida' => 'rs', 'estoque_minimo' => 5])->refresh();
        $requisicao = $this->emCotacao([['descricao' => 'Papel A4', 'quantidade' => 10, 'destino' => 'estoque', 'item_estoque_id' => $papel->id]]);
        $cotacao = $this->cotar($requisicao, $this->papelaria, 18);
        $pedido = $this->servico()->aprovar($this->servico()->escolherCotacao($requisicao, $cotacao, null, 'Único.', $this->coordenador)->refresh(), $this->coordenador);

        $recebimento = $this->servico()->receber($pedido, $this->almoxarife, [$this->itemId($requisicao) => 10], now(), 'NF 9');

        $papel->refresh();
        $this->assertEquals(10.0, (float) $papel->quantidade_atual);
        $this->assertEquals(18.0, (float) $papel->custo_medio);
        $movimento = $papel->movimentacoes()->first();
        $this->assertSame(RecebimentoCompra::class, $movimento->origem_type);
        $this->assertSame($recebimento->id, $movimento->origem_id);
    }

    public function test_bem_duravel_vira_cadastro_no_patrimonio_um_por_unidade(): void
    {
        $requisicao = $this->emCotacao([['descricao' => 'Projetor multimídia', 'quantidade' => 2, 'destino' => DestinoItemCompra::Patrimonio->value]]);
        $cotacao = $this->cotar($requisicao, $this->papelaria, 1500);
        $pedido = $this->servico()->escolherCotacao($requisicao, $cotacao, null, 'Único.', $this->coordenador)->refresh();
        $pedido = $this->servico()->aprovar($pedido, $this->diretor);
        $itemId = $this->itemId($requisicao);

        $this->servico()->receber($pedido, $this->almoxarife, [$itemId => 1], now(), 'NF 1');
        $this->servico()->receber($pedido, $this->almoxarife, [$itemId => 1], now(), 'NF 2');

        $bens = BemPatrimonial::query()->orderBy('id')->get();
        $this->assertCount(2, $bens);
        $this->assertSame(['PC-2026-0001-'.$itemId.'-01', 'PC-2026-0001-'.$itemId.'-02'], $bens->pluck('numero_patrimonio')->all());
        $this->assertEquals(1500.0, (float) $bens[0]->valor_aquisicao);
        $this->assertSame($this->papelaria->id, $bens[0]->fornecedor_id);
    }

    public function test_cancelar_exige_motivo_e_nao_vale_depois_de_entrega(): void
    {
        $requisicao = $this->emCotacao();

        try {
            $this->servico()->cancelar($requisicao, $this->solicitante, '');
            $this->fail('motivo');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('motivo', $e->getMessage());
        }

        $this->servico()->cancelar($requisicao, $this->solicitante, 'Não é mais necessário.');
        $this->assertSame(StatusRequisicaoCompra::Cancelada, $requisicao->refresh()->status);

        $this->expectException(\DomainException::class);
        $this->servico()->cancelar($requisicao, $this->solicitante, 'De novo.');
    }
}

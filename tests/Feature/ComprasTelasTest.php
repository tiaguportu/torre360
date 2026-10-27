<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatusRequisicaoCompra;
use App\Filament\Resources\RequisicoesCompra\Pages\CreateRequisicaoCompra;
use App\Filament\Resources\RequisicoesCompra\Pages\EditRequisicaoCompra;
use App\Filament\Resources\RequisicoesCompra\Pages\ListRequisicoesCompra;
use App\Filament\Resources\RequisicoesCompra\Pages\ViewRequisicaoCompra;
use App\Models\CentroCusto;
use App\Models\Cotacao;
use App\Models\Fornecedor;
use App\Models\ItemEstoque;
use App\Models\PedidoCompra;
use App\Models\PlanoConta;
use App\Models\RecebimentoCompra;
use App\Models\RequisicaoCompra;
use App\Models\User;
use App\Services\ComprasService;
use App\Services\Pendencias\Fontes\ComprasParaCotar;
use App\Services\Pendencias\Fontes\PedidosParaAprovar;
use App\Services\Pendencias\Fontes\PedidosParaReceber;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

/**
 * Compras e cotações (Onda 38), telas: criar e editar o rascunho, o caminho da requisição pelos botões, quem enxerga o quê, a nota
 * fiscal e as filas da Minha Mesa.
 *
 * "Hoje" é segunda-feira, 26/10/2026.
 */
class ComprasTelasTest extends TestCase
{
    use ProibeLazyLoading;
    use RefreshDatabase;

    private User $solicitante;

    private User $outroSolicitante;

    private User $comprador;

    private User $coordenador;

    private User $almoxarife;

    private Fornecedor $papelaria;

    private Fornecedor $atacado;

    private PlanoConta $material;

    private CentroCusto $secretaria;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-26 10:00:00'));
        Storage::fake('local');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $basicas = ['ViewAny:RequisicaoCompra', 'View:RequisicaoCompra', 'Create:RequisicaoCompra', 'Update:RequisicaoCompra'];

        foreach (['solicitante', 'comprador', 'coordenador', 'almoxarife', 'super_admin'] as $papel) {
            Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        }

        foreach (['solicitante', 'comprador', 'coordenador', 'almoxarife'] as $papel) {
            foreach ($basicas as $nome) {
                Role::findByName($papel)->givePermissionTo(Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']));
            }
        }

        foreach (['Cotar:RequisicaoCompra', 'VerTodas:RequisicaoCompra'] as $nome) {
            Role::findByName('comprador')->givePermissionTo(Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']));
        }

        Role::findByName('coordenador')->givePermissionTo(Permission::firstOrCreate(['name' => 'AprovarNivel1:PedidoCompra', 'guard_name' => 'web']));
        Role::findByName('almoxarife')->givePermissionTo(Permission::firstOrCreate(['name' => 'Receber:PedidoCompra', 'guard_name' => 'web']));

        $this->solicitante = $this->usuario('solicitante', 'Ana Solicitante');
        $this->outroSolicitante = $this->usuario('solicitante', 'Bia Solicitante');
        $this->comprador = $this->usuario('comprador', 'Caio Comprador');
        $this->coordenador = $this->usuario('coordenador', 'Carla Coordenadora');
        $this->almoxarife = $this->usuario('almoxarife', 'Alex Almoxarife');

        $this->papelaria = Fornecedor::create(['razao_social' => 'Papelaria Central']);
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

    private function como(User $usuario): User
    {
        session(['active_role' => $usuario->getRoleNames()->first()]);

        return $usuario;
    }

    private function servico(): ComprasService
    {
        return app(ComprasService::class);
    }

    private function requisicao(?User $dono = null, string $titulo = 'Papel para as provas'): RequisicaoCompra
    {
        return $this->servico()->criarRequisicao($dono ?? $this->solicitante, [
            'titulo' => $titulo,
            'justificativa' => 'Acabou o estoque.',
            'centro_custo_id' => $this->secretaria->id,
            'plano_conta_id' => $this->material->id,
        ], [['descricao' => 'Resma de papel A4', 'quantidade' => 10, 'unidade_medida' => 'rs', 'estimativa_unitaria' => 25]]);
    }

    private function emCotacao(?User $dono = null): RequisicaoCompra
    {
        return $this->servico()->enviarParaCotacao($this->requisicao($dono), $dono ?? $this->solicitante);
    }

    private function cotar(RequisicaoCompra $requisicao, Fornecedor $fornecedor, float $preco): Cotacao
    {
        $precos = $requisicao->itens()->pluck('id')->mapWithKeys(fn (int $id): array => [$id => $preco])->all();

        return $this->servico()->registrarCotacao($requisicao, $fornecedor, $precos, ['prazo_entrega_dias' => 5, 'prazo_pagamento_dias' => 30], $this->comprador);
    }

    private function pedidoAguardando(?User $dono = null, float $preco = 20.0): PedidoCompra
    {
        $requisicao = $this->emCotacao($dono);
        $cotacao = $this->cotar($requisicao, $this->papelaria, $preco);

        return $this->servico()->escolherCotacao($requisicao, $cotacao, null, 'Fornecedor único.', $this->comprador);
    }

    private function pedidoAprovado(?User $dono = null): PedidoCompra
    {
        $pedido = $this->pedidoAguardando($dono);
        $this->servico()->aprovar($pedido, $this->coordenador);

        return $pedido->refresh();
    }

    private function tela(RequisicaoCompra $requisicao, User $usuario)
    {
        return Livewire::actingAs($this->como($usuario))->test(ViewRequisicaoCompra::class, ['record' => $requisicao->getKey()]);
    }

    // --- Criar e editar ----------------------------------------------------------------------------------------------

    public function test_criar_pela_tela_gera_o_rascunho_com_numero_e_itens(): void
    {
        Livewire::actingAs($this->como($this->solicitante))
            ->test(CreateRequisicaoCompra::class)
            ->fillForm([
                'titulo' => 'Toner da impressora',
                'centro_custo_id' => $this->secretaria->id,
                'plano_conta_id' => $this->material->id,
                'urgencia' => 'urgente',
                'justificativa' => 'Acabou.',
                'itens' => [['descricao' => 'Toner preto', 'quantidade' => 2, 'unidade_medida' => 'un', 'estimativa_unitaria' => 300, 'destino' => 'consumo']],
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $requisicao = RequisicaoCompra::query()->where('titulo', 'Toner da impressora')->firstOrFail();

        $this->assertSame('REQ-2026-0001', $requisicao->numero);
        $this->assertSame(StatusRequisicaoCompra::Rascunho, $requisicao->status);
        $this->assertSame($this->solicitante->id, $requisicao->solicitante_user_id);
        $this->assertTrue($requisicao->urgente());
        $this->assertSame(1, $requisicao->itens()->count());
    }

    public function test_item_de_estoque_e_obrigatorio_quando_o_destino_e_estoque(): void
    {
        Livewire::actingAs($this->como($this->solicitante))
            ->test(CreateRequisicaoCompra::class)
            ->fillForm([
                'titulo' => 'Papel',
                'justificativa' => 'Reposição.',
                'urgencia' => 'normal',
                'itens' => [['descricao' => 'Resma', 'quantidade' => 5, 'unidade_medida' => 'rs', 'destino' => 'estoque']],
            ])
            ->call('create')
            ->assertHasFormErrors(['itens.0.item_estoque_id' => 'required']);

        $this->assertSame(0, RequisicaoCompra::query()->count());

        $item = ItemEstoque::create(['nome' => 'Papel A4', 'unidade_medida' => 'rs', 'estoque_minimo' => 5]);

        Livewire::actingAs($this->como($this->solicitante))
            ->test(CreateRequisicaoCompra::class)
            ->fillForm([
                'titulo' => 'Papel',
                'justificativa' => 'Reposição.',
                'urgencia' => 'normal',
                'itens' => [['descricao' => 'Resma', 'quantidade' => 5, 'unidade_medida' => 'rs', 'destino' => 'estoque', 'item_estoque_id' => $item->id]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame($item->id, RequisicaoCompra::query()->firstOrFail()->itens()->firstOrFail()->item_estoque_id);
    }

    public function test_editar_o_rascunho_troca_os_itens(): void
    {
        $requisicao = $this->requisicao();

        Livewire::actingAs($this->como($this->solicitante))
            ->test(EditRequisicaoCompra::class, ['record' => $requisicao->getKey()])
            ->assertFormSet(['titulo' => 'Papel para as provas'])
            ->fillForm([
                'titulo' => 'Papel e canetas',
                'itens' => [
                    ['descricao' => 'Resma', 'quantidade' => 4, 'unidade_medida' => 'rs', 'destino' => 'consumo'],
                    ['descricao' => 'Caneta azul', 'quantidade' => 50, 'unidade_medida' => 'un', 'destino' => 'consumo'],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Papel e canetas', $requisicao->refresh()->titulo);
        $this->assertSame(['Resma', 'Caneta azul'], $requisicao->itens()->orderBy('id')->pluck('descricao')->all());
    }

    public function test_requisicao_enviada_nao_abre_para_edicao(): void
    {
        $requisicao = $this->emCotacao();

        Livewire::actingAs($this->como($this->solicitante))
            ->test(EditRequisicaoCompra::class, ['record' => $requisicao->getKey()])
            ->assertForbidden();
    }

    // --- Quem enxerga o quê ------------------------------------------------------------------------------------------

    public function test_a_lista_mostra_so_as_proprias_requisicoes_a_menos_que_possa_ver_todas(): void
    {
        $minha = $this->requisicao($this->solicitante, 'Minha compra');
        $alheia = $this->requisicao($this->outroSolicitante, 'Compra da Bia');

        Livewire::actingAs($this->como($this->solicitante))
            ->test(ListRequisicoesCompra::class)
            ->assertCanSeeTableRecords([$minha])
            ->assertCanNotSeeTableRecords([$alheia]);

        Livewire::actingAs($this->como($this->comprador))
            ->test(ListRequisicoesCompra::class)
            ->assertCanSeeTableRecords([$minha, $alheia]);
    }

    public function test_quem_decide_enxerga_o_que_precisa_decidir_e_nada_alem(): void
    {
        $rascunho = $this->requisicao($this->outroSolicitante, 'Rascunho alheio');
        $aguardando = $this->pedidoAguardando($this->outroSolicitante)->requisicao()->firstOrFail();
        $cara = $this->pedidoAguardando($this->outroSolicitante, 2000)->requisicao()->firstOrFail();

        // Coordenação aprova até o nível 1: não vê o rascunho do outro nem o pedido de R$ 20 mil.
        Livewire::actingAs($this->como($this->coordenador))
            ->test(ListRequisicoesCompra::class)
            ->assertCanSeeTableRecords([$aguardando])
            ->assertCanNotSeeTableRecords([$rascunho, $cara]);

        // Quem recebe só enxerga a compra depois de aprovada.
        Livewire::actingAs($this->como($this->almoxarife))->test(ListRequisicoesCompra::class)->assertCanNotSeeTableRecords([$aguardando]);
        $this->como($this->coordenador);
        $this->servico()->aprovar($aguardando->pedido()->firstOrFail(), $this->coordenador);
        Livewire::actingAs($this->como($this->almoxarife))->test(ListRequisicoesCompra::class)->assertCanSeeTableRecords([$aguardando])->assertCanNotSeeTableRecords([$rascunho]);

        // Quem só cota (sem "Ver todas") vê tudo que saiu do rascunho, mas o rascunho de outra pessoa continua dela.
        Role::firstOrCreate(['name' => 'cotador', 'guard_name' => 'web']);

        foreach (['ViewAny:RequisicaoCompra', 'View:RequisicaoCompra', 'Cotar:RequisicaoCompra'] as $nome) {
            Role::findByName('cotador')->givePermissionTo(Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']));
        }

        $cotador = $this->usuario('cotador', 'Cíntia Cotadora');

        Livewire::actingAs($this->como($cotador))->test(ListRequisicoesCompra::class)->assertCanSeeTableRecords([$aguardando, $cara])->assertCanNotSeeTableRecords([$rascunho]);
    }

    public function test_quem_nao_e_dono_nem_ve_todas_nao_abre_a_requisicao(): void
    {
        $alheia = $this->requisicao($this->outroSolicitante);

        $this->actingAs($this->como($this->solicitante))
            ->get(route('filament.admin.resources.suprimentos.compras.view', ['record' => $alheia]))
            ->assertNotFound();
    }

    // --- O caminho pelos botões --------------------------------------------------------------------------------------

    public function test_botoes_seguem_o_passo_e_o_perfil(): void
    {
        $rascunho = $this->requisicao();

        $this->tela($rascunho, $this->solicitante)->assertActionVisible('enviar')->assertActionVisible('editar')->assertActionHidden('cotacao')->assertActionVisible('cancelar');
        $this->tela($rascunho, $this->comprador)->assertActionHidden('enviar')->assertActionHidden('editar')->assertActionHidden('cotacao');

        $this->servico()->enviarParaCotacao($rascunho, $this->solicitante);
        $emCotacao = $rascunho->refresh();

        $this->tela($emCotacao, $this->solicitante)->assertActionHidden('enviar')->assertActionHidden('editar')->assertActionHidden('cotacao');
        $this->tela($emCotacao, $this->comprador)->assertActionVisible('cotacao')->assertActionHidden('escolher')->assertActionVisible('cancelar');

        $this->cotar($emCotacao, $this->papelaria, 20);

        $this->tela($emCotacao, $this->comprador)->assertActionVisible('escolher')->assertActionVisible('remover_cotacao');
        $this->tela($emCotacao, $this->solicitante)->assertActionHidden('escolher');
    }

    public function test_enviar_para_cotacao_pela_tela(): void
    {
        $requisicao = $this->requisicao();

        $this->tela($requisicao, $this->solicitante)->callAction('enviar')->assertNotified('Requisição enviada para cotação');

        $this->assertSame(StatusRequisicaoCompra::EmCotacao, $requisicao->refresh()->status);
    }

    public function test_registrar_cotacao_exige_o_preco_de_todos_os_itens_e_grava_a_proposta(): void
    {
        $requisicao = $this->emCotacao();
        $item = $requisicao->itens()->firstOrFail();

        $this->tela($requisicao, $this->comprador)
            ->callAction('cotacao', data: ['fornecedor_id' => $this->papelaria->id, "preco_{$item->id}" => 22.5, 'frete' => 15, 'prazo_entrega_dias' => 4, 'prazo_pagamento_dias' => 28])
            ->assertHasNoActionErrors()
            ->assertNotified('Cotação registrada');

        $cotacao = $requisicao->cotacoes()->firstOrFail();
        $this->assertSame($this->papelaria->id, $cotacao->fornecedor_id);
        $this->assertSame(4, $cotacao->prazo_entrega_dias);
        $this->assertEquals(240.0, $this->servico()->mapaComparativo($requisicao)['cotacoes'][0]['total']);
    }

    public function test_escolher_a_cotacao_abre_o_pedido_e_o_mapa_aparece_na_tela(): void
    {
        $requisicao = $this->emCotacao();
        $barata = $this->cotar($requisicao, $this->papelaria, 20);
        $cara = $this->cotar($requisicao, $this->atacado, 24);

        $this->tela($requisicao, $this->comprador)->assertSee('Papelaria Central')->assertSee('Atacado Sul')->assertSee('menor valor');

        $this->tela($requisicao, $this->comprador)
            ->callAction('escolher', data: ['cotacao_id' => $cara->id])
            ->assertNotified('Escolha recusada');
        $this->assertSame(StatusRequisicaoCompra::EmCotacao, $requisicao->refresh()->status);

        $this->tela($requisicao, $this->comprador)
            ->callAction('escolher', data: ['cotacao_id' => $barata->id])
            ->assertNotified('Pedido aberto: aguarda aprovação');

        $requisicao->refresh();
        $this->assertSame(StatusRequisicaoCompra::AguardandoAprovacao, $requisicao->status);
        $this->assertSame($barata->id, $requisicao->pedido()->firstOrFail()->cotacao_id);
    }

    public function test_so_quem_tem_a_alcada_e_nao_e_o_solicitante_aprova(): void
    {
        $pedido = $this->pedidoAguardando();
        $requisicao = $pedido->requisicao()->firstOrFail();

        $this->tela($requisicao, $this->coordenador)->assertActionVisible('aprovar')->assertActionVisible('recusar');
        $this->tela($requisicao, $this->solicitante)->assertActionHidden('aprovar')->assertActionHidden('recusar');
        $this->tela($requisicao, $this->comprador)->assertActionHidden('aprovar');
    }

    public function test_super_admin_tambem_respeita_a_segregacao_de_funcoes(): void
    {
        $dono = $this->usuario('super_admin', 'Sara Super');
        $pedido = $this->pedidoAguardando($dono);
        $requisicao = $pedido->requisicao()->firstOrFail();

        // O Gate::before libera qualquer habilidade ao super admin, mas quem solicitou a compra não a aprova.
        $this->tela($requisicao, $dono)->assertActionHidden('aprovar')->assertActionHidden('enviar');

        $outro = $this->usuario('super_admin', 'Saulo Super');
        $this->tela($requisicao, $outro)->assertActionVisible('aprovar');
    }

    public function test_aprovar_pela_tela(): void
    {
        $pedido = $this->pedidoAguardando();
        $requisicao = $pedido->requisicao()->firstOrFail();

        $this->tela($requisicao, $this->coordenador)->callAction('aprovar')->assertNotified('Pedido aprovado');

        $this->assertSame(StatusRequisicaoCompra::Aprovada, $requisicao->refresh()->status);
        $this->assertSame($this->coordenador->id, $pedido->refresh()->aprovado_por_user_id);
    }

    public function test_recusar_exige_motivo_e_pode_devolver_para_nova_cotacao(): void
    {
        $pedido = $this->pedidoAguardando();
        $requisicao = $pedido->requisicao()->firstOrFail();

        $this->tela($requisicao, $this->coordenador)->callAction('recusar', data: ['motivo' => ''])->assertHasActionErrors(['motivo' => 'required']);

        $this->tela($requisicao, $this->coordenador)->callAction('recusar', data: ['motivo' => 'Preço acima do praticado.', 'devolver' => true])->assertNotified('Pedido recusado');

        $requisicao->refresh();
        $this->assertSame(StatusRequisicaoCompra::EmCotacao, $requisicao->status);
        $this->assertSame('Preço acima do praticado.', $requisicao->motivo_recusa);
        $this->assertSame(0, PedidoCompra::query()->count());
    }

    public function test_quem_aprovou_nao_recebe_e_o_almoxarife_recebe_com_a_nota(): void
    {
        $pedido = $this->pedidoAprovado();
        $requisicao = $pedido->requisicao()->firstOrFail();
        $item = $requisicao->itens()->firstOrFail();

        $this->tela($requisicao, $this->coordenador)->assertActionHidden('receber');
        $this->tela($requisicao, $this->almoxarife)->assertActionVisible('receber');

        $nota = UploadedFile::fake()->createWithContent('nota.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF");

        $this->tela($requisicao, $this->almoxarife)
            ->callAction('receber', data: ["qtd_{$item->id}" => 10, 'data_recebimento' => '2026-10-26', 'nota_fiscal_numero' => '4512', 'nota_fiscal' => $nota, 'divergencia' => false])
            ->assertHasNoActionErrors()
            ->assertNotified('Recebimento registrado');

        $recebimento = RecebimentoCompra::query()->firstOrFail();

        $this->assertSame(StatusRequisicaoCompra::Recebida, $requisicao->refresh()->status);
        $this->assertSame('4512', $recebimento->nota_fiscal_numero);
        $this->assertNotNull($recebimento->nota_fiscal_path);
        Storage::disk('local')->assertExists($recebimento->nota_fiscal_path);
    }

    public function test_recebimento_com_divergencia_sem_descricao_e_recusado(): void
    {
        $pedido = $this->pedidoAprovado();
        $requisicao = $pedido->requisicao()->firstOrFail();
        $item = $requisicao->itens()->firstOrFail();

        $this->tela($requisicao, $this->almoxarife)
            ->callAction('receber', data: ["qtd_{$item->id}" => 4, 'data_recebimento' => '2026-10-26', 'nota_fiscal_numero' => '77', 'divergencia' => true])
            ->assertNotified('Recebimento recusado');

        $this->assertSame(0, RecebimentoCompra::query()->count());
        $this->assertSame(StatusRequisicaoCompra::Aprovada, $requisicao->refresh()->status);
    }

    public function test_cancelar_pede_motivo_e_encerra(): void
    {
        $requisicao = $this->emCotacao();

        $this->tela($requisicao, $this->comprador)->assertActionVisible('cancelar');
        $this->tela($requisicao, $this->solicitante)->callAction('cancelar', data: ['motivo' => ''])->assertHasActionErrors(['motivo' => 'required']);
        $this->tela($requisicao, $this->solicitante)->callAction('cancelar', data: ['motivo' => 'Não é mais necessário.'])->assertNotified('Requisição cancelada');

        $this->assertSame(StatusRequisicaoCompra::Cancelada, $requisicao->refresh()->status);
    }

    // --- Nota fiscal -------------------------------------------------------------------------------------------------

    private function recebimentoComNota(): RecebimentoCompra
    {
        $pedido = $this->pedidoAprovado();
        $item = $pedido->requisicao()->firstOrFail()->itens()->firstOrFail();

        return $this->servico()->receber($pedido, $this->almoxarife, [$item->id => 10], now(), '4512', ['conteudo' => "%PDF-1.4\n%%EOF", 'extensao' => 'pdf']);
    }

    public function test_nota_fiscal_abre_para_o_dono_e_para_quem_ve_todas_e_nao_para_os_outros(): void
    {
        $recebimento = $this->recebimentoComNota();
        $url = route('compras.nota-fiscal', $recebimento);

        $dono = $this->get($url);
        $this->assertGuest();
        $dono->assertRedirect();

        $this->actingAs($this->como($this->solicitante))->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($this->como($this->comprador))->get($url)->assertOk();
        $this->actingAs($this->como($this->outroSolicitante))->get($url)->assertForbidden();
    }

    // --- Minha Mesa --------------------------------------------------------------------------------------------------

    public function test_fila_de_compras_para_cotar_conta_urgente_e_antiga_como_atrasada(): void
    {
        $normal = $this->emCotacao();
        $urgente = $this->servico()->enviarParaCotacao($this->requisicao($this->solicitante, 'Urgente'), $this->solicitante);
        $urgente->update(['urgencia' => 'urgente']);

        $fila = new ComprasParaCotar;

        $this->assertSame(2, $fila->contar($this->comprador));
        $this->assertSame(1, $fila->atrasados($this->comprador));

        $this->travel(4)->days();
        $this->assertSame(2, $fila->atrasados($this->comprador));
        $this->assertSame('heroicon-o-shopping-cart', $fila->icone());
        $this->assertSame($urgente->id, collect($fila->itens($this->comprador, 5))->first()?->url ? $urgente->id : null);
        $this->assertSame('Cotar:RequisicaoCompra', $fila->permissao());
        $this->assertNotNull($normal->id);
    }

    public function test_fila_de_pedidos_para_aprovar_respeita_a_alcada_e_a_segregacao(): void
    {
        $this->pedidoAguardando($this->solicitante, 20);                 // R$ 200: nível 1
        $this->pedidoAguardando($this->outroSolicitante, 2000);          // R$ 20.000: nível 3
        $coordenacaoQueSolicitou = $this->usuario('coordenador', 'Cláudia Coordenadora');
        $this->pedidoAguardando($coordenacaoQueSolicitou, 20);           // do próprio coordenador

        $fila = new PedidosParaAprovar;

        $this->assertSame(2, $fila->contar($this->coordenador), 'o coordenador só aprova nível 1');
        $this->assertSame(1, $fila->contar($coordenacaoQueSolicitou), 'ele não vê o pedido que ele mesmo solicitou');
        $this->assertSame(0, $fila->contar($this->solicitante), 'quem não tem alçada não vê nada');
        $this->assertSame(0, $fila->atrasados($this->coordenador));

        $this->travel(3)->days();
        $this->assertSame(2, $fila->atrasados($this->coordenador));
        $this->assertCount(2, $fila->itens($this->coordenador, 5));
    }

    public function test_fila_de_pedidos_para_receber_ignora_quem_aprovou_e_marca_o_atraso_pelo_prazo_da_cotacao(): void
    {
        $this->pedidoAprovado();

        $fila = new PedidosParaReceber;

        $this->assertSame(1, $fila->contar($this->almoxarife));
        $this->assertSame(0, $fila->contar($this->coordenador), 'quem aprovou não recebe');
        $this->assertSame(0, $fila->atrasados($this->almoxarife));

        $this->travel(4)->days();
        $this->assertSame(0, $fila->atrasados($this->almoxarife), 'o prazo prometido é de 5 dias');

        $this->travel(2)->days();
        $this->assertSame(1, $fila->atrasados($this->almoxarife));
        $this->assertTrue($fila->itens($this->almoxarife, 5)[0]->atrasado);
    }
}

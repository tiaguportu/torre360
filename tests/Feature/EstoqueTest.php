<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TipoMovimentacaoEstoque;
use App\Filament\Resources\ItensEstoque\Pages\CreateItemEstoque;
use App\Filament\Resources\ItensEstoque\Pages\EditItemEstoque;
use App\Filament\Resources\ItensEstoque\Pages\ListItensEstoque;
use App\Filament\Resources\ItensEstoque\RelationManagers\MovimentacoesRelationManager;
use App\Models\CentroCusto;
use App\Models\ItemEstoque;
use App\Models\User;
use App\Services\EstoqueService;
use App\Services\Pendencias\Fontes\EstoqueAbaixoDoMinimo;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Estoque de consumíveis (Onda 16, fase 2): movimentação com saldo e custo médio, a trilha, o aviso de mínimo e as telas.
 */
class EstoqueTest extends TestCase
{
    use RefreshDatabase;

    private User $gestor;

    private User $leitor;

    private CentroCusto $secretaria;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['admin', 'secretaria'] as $papel) {
            Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        }

        foreach (['ViewAny:ItemEstoque', 'View:ItemEstoque', 'Create:ItemEstoque', 'Update:ItemEstoque', 'Delete:ItemEstoque', 'Movimentar:ItemEstoque'] as $nome) {
            Role::findByName('admin')->givePermissionTo(Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']));
        }

        foreach (['ViewAny:ItemEstoque', 'View:ItemEstoque'] as $nome) {
            Role::findByName('secretaria')->givePermissionTo(Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']));
        }

        $this->gestor = $this->usuario('admin');
        $this->leitor = $this->usuario('secretaria');
        $this->secretaria = CentroCusto::create(['nome' => 'Secretaria', 'is_active' => true]);
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

    private function item(array $atributos = []): ItemEstoque
    {
        return ItemEstoque::create($atributos + ['nome' => 'Papel A4', 'unidade_medida' => 'rs', 'estoque_minimo' => 5])->refresh();
    }

    private function servico(): EstoqueService
    {
        return app(EstoqueService::class);
    }

    public function test_entrada_soma_o_saldo_e_calcula_o_custo_medio_ponderado(): void
    {
        $item = $this->item();

        $this->servico()->entrada($item, 10, 20.0, null, $this->gestor, 'NF 100');
        $this->assertEquals(10.0, (float) $item->refresh()->quantidade_atual);
        $this->assertEquals(20.0, (float) $item->custo_medio, 'primeira entrada define o custo');

        $this->servico()->entrada($item, 30, 24.0, null, $this->gestor);
        $item->refresh();

        $this->assertEquals(40.0, (float) $item->quantidade_atual);
        $this->assertEquals(23.0, (float) $item->custo_medio, '(10×20 + 30×24) ÷ 40');
        $this->assertEquals(920.0, $item->valorEmEstoque());
        $this->assertEquals(920.0, $this->servico()->valorTotal());
    }

    public function test_entrada_sem_custo_nao_mexe_no_custo_medio(): void
    {
        $item = $this->item();
        $this->servico()->entrada($item, 10, 5.0);
        $this->servico()->entrada($item, 10);

        $this->assertEquals(20.0, (float) $item->refresh()->quantidade_atual);
        $this->assertEquals(5.0, (float) $item->custo_medio);
    }

    public function test_saida_baixa_o_saldo_registra_o_setor_e_recusa_o_que_falta(): void
    {
        $item = $this->item();
        $this->servico()->entrada($item, 10, 20.0);

        $saida = $this->servico()->saida($item, 4, $this->secretaria, 'Impressão de provas', $this->gestor);

        $this->assertSame(TipoMovimentacaoEstoque::Saida, $saida->tipo);
        $this->assertEquals(-4.0, (float) $saida->quantidade);
        $this->assertEquals(6.0, (float) $saida->saldo_apos);
        $this->assertSame($this->secretaria->id, $saida->centro_custo_id);
        $this->assertEquals(20.0, (float) $saida->custo_unitario, 'a saída sai pelo custo médio');

        try {
            $this->servico()->saida($item, 6.001);
            $this->fail('não pode ficar negativo');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Saldo insuficiente', $e->getMessage());
        }

        $this->assertEquals(6.0, (float) $item->refresh()->quantidade_atual, 'a tentativa recusada não muda nada');
        $this->servico()->saida($item, 6);
        $this->assertEquals(0.0, (float) $item->refresh()->quantidade_atual);
    }

    public function test_ajuste_acerta_ao_contado_e_exige_motivo(): void
    {
        $item = $this->item();
        $this->servico()->entrada($item, 10);

        $ajuste = $this->servico()->ajustar($item, 7.5, 'Contagem de outubro', $this->gestor);

        $this->assertSame(TipoMovimentacaoEstoque::Ajuste, $ajuste->tipo);
        $this->assertEquals(-2.5, (float) $ajuste->quantidade);
        $this->assertEquals(7.5, (float) $item->refresh()->quantidade_atual);

        foreach ([[7.5, 'Igual', 'não há o que ajustar'], [-1.0, 'Negativo', 'negativa'], [3.0, '   ', 'motivo']] as [$contado, $motivo, $trecho]) {
            try {
                $this->servico()->ajustar($item, $contado, $motivo);
                $this->fail('devia recusar');
            } catch (\DomainException $e) {
                $this->assertStringContainsString($trecho, $e->getMessage());
            }
        }
    }

    public function test_quantidade_precisa_ser_positiva_e_item_inativo_nao_movimenta(): void
    {
        $item = $this->item();

        foreach ([fn () => $this->servico()->entrada($item, 0), fn () => $this->servico()->saida($item, -1), fn () => $this->servico()->entrada($item, 1, -2.0)] as $tentativa) {
            try {
                $tentativa();
                $this->fail('devia recusar');
            } catch (\DomainException) {
                $this->addToAssertionCount(1);
            }
        }

        $item->update(['is_active' => false]);

        $this->expectException(\DomainException::class);
        $this->servico()->entrada($item, 1);
    }

    public function test_movimentacao_deixa_a_trilha_com_o_saldo_de_cada_passo(): void
    {
        $item = $this->item();
        $this->servico()->entrada($item, 10);
        $this->servico()->saida($item, 3);
        $this->servico()->ajustar($item, 5, 'Inventário');

        $saldos = $item->movimentacoes()->reorder('id')->get()->map(fn ($m) => [$m->tipo->value, (float) $m->quantidade, (float) $m->saldo_apos])->all();

        $this->assertSame([['entrada', 10.0, 10.0], ['saida', -3.0, 7.0], ['ajuste', -2.0, 5.0]], $saldos);
    }

    public function test_abaixo_do_minimo_considera_so_item_ativo_com_minimo_definido(): void
    {
        $abaixo = $this->item(['nome' => 'Abaixo']);
        $this->servico()->entrada($abaixo, 3);
        $noMinimo = $this->item(['nome' => 'No mínimo']);
        $this->servico()->entrada($noMinimo, 5);
        $folgado = $this->item(['nome' => 'Folgado']);
        $this->servico()->entrada($folgado, 50);
        $semMinimo = $this->item(['nome' => 'Sem mínimo', 'estoque_minimo' => 0]);
        $inativo = $this->item(['nome' => 'Inativo', 'is_active' => false]);

        $nomes = ItemEstoque::query()->abaixoDoMinimo()->orderBy('nome')->pluck('nome')->all();

        $this->assertSame(['Abaixo', 'No mínimo'], $nomes);
        $this->assertTrue($abaixo->refresh()->abaixoDoMinimo());
        $this->assertFalse($semMinimo->abaixoDoMinimo());
        $this->assertFalse($inativo->abaixoDoMinimo());
        $this->assertFalse($folgado->refresh()->abaixoDoMinimo());
    }

    public function test_fila_da_mesa_lista_os_itens_e_marca_o_zerado_como_atrasado(): void
    {
        $zerado = $this->item(['nome' => 'Zerado']);
        $baixo = $this->item(['nome' => 'Baixo']);
        $this->servico()->entrada($baixo, 2);

        $fila = new EstoqueAbaixoDoMinimo;

        $this->assertSame('ViewAny:ItemEstoque', $fila->permissao());
        $this->assertSame(2, $fila->contar($this->gestor));
        $this->assertSame(1, $fila->atrasados($this->gestor));

        $itens = $fila->itens($this->gestor, 5);
        $this->assertSame('Zerado', $itens[0]->titulo);
        $this->assertTrue($itens[0]->atrasado);
        $this->assertStringContainsString('mínimo 5 rs', $itens[1]->detalhe);
        $this->assertFalse($itens[1]->atrasado);
        $this->assertNotNull($zerado);
    }

    public function test_politica_separa_cadastrar_de_movimentar_e_protege_item_com_historico(): void
    {
        $item = $this->item();

        $this->como($this->leitor);
        $this->assertTrue($this->leitor->can('view', $item));
        $this->assertFalse($this->leitor->can('movimentar', $item));
        $this->assertFalse($this->leitor->can('update', $item));

        $this->como($this->gestor);
        $this->assertTrue($this->gestor->can('movimentar', $item));
        $this->assertTrue($this->gestor->can('delete', $item));

        $this->servico()->entrada($item, 1);
        $this->assertFalse($this->gestor->can('delete', $item), 'item com movimentação não se exclui');

        $item->update(['is_active' => false]);
        $this->assertFalse($this->gestor->can('movimentar', $item->refresh()), 'inativo não movimenta');
    }

    public function test_tela_registra_entrada_saida_e_ajuste_para_quem_pode(): void
    {
        $item = $this->item();

        $tela = fn () => Livewire::actingAs($this->como($this->gestor))->test(ListItensEstoque::class);

        $tela()->callAction(TestAction::make('entrada')->table($item), ['quantidade' => 12, 'custo' => 4.5, 'motivo' => 'NF 77'])->assertNotified('Entrada registrada');
        $tela()->callAction(TestAction::make('saida')->table($item), ['quantidade' => 2, 'centro_custo_id' => $this->secretaria->id, 'motivo' => 'Provas'])->assertNotified('Saída registrada');
        $tela()->callAction(TestAction::make('ajuste')->table($item), ['contado' => 9, 'motivo' => 'Contagem'])->assertNotified('Ajuste registrado');

        $item->refresh();
        $this->assertEquals(9.0, (float) $item->quantidade_atual);
        $this->assertSame(3, $item->movimentacoes()->count());
    }

    public function test_tela_avisa_saida_acima_do_saldo_e_esconde_as_acoes_de_quem_so_le(): void
    {
        $item = $this->item();
        $this->servico()->entrada($item, 2);

        Livewire::actingAs($this->como($this->gestor))
            ->test(ListItensEstoque::class)
            ->callAction(TestAction::make('saida')->table($item), ['quantidade' => 5])
            ->assertNotified('Não foi possível movimentar');

        $this->assertEquals(2.0, (float) $item->refresh()->quantidade_atual);

        Livewire::actingAs($this->como($this->leitor))
            ->test(ListItensEstoque::class)
            ->assertSuccessful()
            ->assertActionHidden(TestAction::make('entrada')->table($item))
            ->assertActionHidden(TestAction::make('saida')->table($item));
    }

    public function test_cadastro_pela_tela_e_historico_na_edicao(): void
    {
        Livewire::actingAs($this->como($this->gestor))
            ->test(CreateItemEstoque::class)
            ->fillForm(['nome' => 'Detergente', 'unidade_medida' => 'l', 'estoque_minimo' => 3, 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $item = ItemEstoque::query()->where('nome', 'Detergente')->firstOrFail();
        $this->assertEquals(0.0, (float) $item->quantidade_atual, 'o cadastro não leva saldo');

        $this->servico()->entrada($item, 5, 3.0, null, $this->gestor);

        Livewire::actingAs($this->como($this->gestor))
            ->test(MovimentacoesRelationManager::class, ['ownerRecord' => $item, 'pageClass' => EditItemEstoque::class])
            ->assertSuccessful()
            ->assertCanSeeTableRecords($item->movimentacoes);
    }
}

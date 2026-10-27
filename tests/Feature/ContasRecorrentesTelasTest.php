<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatusContaPagar;
use App\Filament\Pages\FluxoProjetado;
use App\Filament\Resources\ContaPagars\Pages\ListContaPagars;
use App\Filament\Resources\ContasRecorrentes\Pages\CreateContaRecorrente;
use App\Filament\Resources\ContasRecorrentes\Pages\EditContaRecorrente;
use App\Filament\Resources\ContasRecorrentes\Pages\ListContasRecorrentes;
use App\Models\Banco;
use App\Models\ContaPagar;
use App\Models\ContaRecorrente;
use App\Models\TransacaoBancaria;
use App\Models\User;
use App\Services\ContaRecorrenteService;
use App\Services\Pendencias\Fontes\ReajustesDeContrato;
use App\Services\Pendencias\Fontes\RecorrentesParaRevisar;
use App\Services\Pendencias\Fontes\SaldoProjetadoNegativo;
use App\Services\Relatorios\Fontes\FluxoProjetadoSemanal;
use Carbon\Carbon;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

/**
 * Contas recorrentes e fluxo projetado (Onda 78), telas: cadastro, geração, reajuste, revisão do valor estimado na conta a pagar,
 * página do fluxo, filas da Minha Mesa e a fonte dos relatórios.
 *
 * "Hoje" é segunda-feira, 26/10/2026.
 */
class ContasRecorrentesTelasTest extends TestCase
{
    use ProibeLazyLoading;
    use RefreshDatabase;

    private User $financeiro;

    private User $leitor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-26 09:00:00'));
        Cache::flush();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['financeiro', 'leitor'] as $papel) {
            Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        }

        foreach (['ViewAny:ContaRecorrente', 'View:ContaRecorrente', 'Create:ContaRecorrente', 'Update:ContaRecorrente', 'Delete:ContaRecorrente', 'View:FluxoProjetado', 'ViewAny:ContaPagar', 'View:ContaPagar', 'Update:ContaPagar'] as $nome) {
            Role::findByName('financeiro')->givePermissionTo(Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']));
        }

        foreach (['ViewAny:ContaRecorrente', 'View:ContaRecorrente'] as $nome) {
            Role::findByName('leitor')->givePermissionTo(Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']));
        }

        $this->financeiro = $this->usuario('financeiro');
        $this->leitor = $this->usuario('leitor');
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
     * @param  array<string, mixed>  $atributos
     */
    private function recorrente(array $atributos = []): ContaRecorrente
    {
        return ContaRecorrente::create($atributos + ['descricao' => 'Aluguel do prédio', 'periodicidade' => 'mensal', 'dia_vencimento' => 5, 'valor' => 4000, 'vigencia_inicio' => '2026-01-01', 'antecedencia_dias' => 10])->refresh();
    }

    // --- Cadastro ----------------------------------------------------------------------------------------------------

    public function test_cadastrar_pela_tela(): void
    {
        Livewire::actingAs($this->como($this->financeiro))
            ->test(CreateContaRecorrente::class)
            ->fillForm(['descricao' => 'Internet', 'periodicidade' => 'mensal', 'dia_vencimento' => 12, 'valor' => 350, 'vigencia_inicio' => '2026-11-01', 'antecedencia_dias' => 7, 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $modelo = ContaRecorrente::query()->where('descricao', 'Internet')->firstOrFail();
        $this->assertEquals(350.0, (float) $modelo->valor);
        $this->assertSame(12, $modelo->dia_vencimento);
    }

    public function test_dia_de_vencimento_fora_do_mes_e_vigencia_invertida_sao_recusados(): void
    {
        Livewire::actingAs($this->como($this->financeiro))
            ->test(CreateContaRecorrente::class)
            ->fillForm(['descricao' => 'X', 'periodicidade' => 'mensal', 'dia_vencimento' => 40, 'valor' => 10, 'vigencia_inicio' => '2026-11-01', 'vigencia_fim' => '2026-10-01', 'antecedencia_dias' => 7])
            ->call('create')
            ->assertHasFormErrors(['dia_vencimento', 'vigencia_fim']);
    }

    public function test_quem_so_le_ve_a_lista_mas_nao_cria_nem_reajusta(): void
    {
        $modelo = $this->recorrente();

        Livewire::actingAs($this->como($this->leitor))
            ->test(ListContasRecorrentes::class)
            ->assertCanSeeTableRecords([$modelo])
            ->assertActionHidden('gerar')
            ->assertActionHidden(TestAction::make('reajustar')->table($modelo));

        Livewire::actingAs($this->como($this->financeiro))
            ->test(ListContasRecorrentes::class)
            ->assertActionVisible('gerar')
            ->assertActionVisible(TestAction::make('reajustar')->table($modelo));
    }

    public function test_botao_gerar_contas_cria_as_contas_do_mes_sem_duplicar(): void
    {
        $modelo = $this->recorrente();

        $tela = Livewire::actingAs($this->como($this->financeiro))->test(ListContasRecorrentes::class);

        $tela->callAction('gerar')->assertNotified('2 conta(s) gerada(s)');
        $this->assertSame(2, $modelo->contas()->count());

        $tela->callAction('gerar')->assertNotified('0 conta(s) gerada(s)');
        $this->assertSame(2, $modelo->contas()->count());
    }

    public function test_reajuste_pela_tabela_atualiza_o_modelo_e_as_contas_abertas(): void
    {
        $modelo = $this->recorrente(['proximo_reajuste_em' => '2026-11-10']);
        app(ContaRecorrenteService::class)->gerar();

        Livewire::actingAs($this->como($this->financeiro))
            ->test(ListContasRecorrentes::class)
            ->callAction(TestAction::make('reajustar')->table($modelo), ['valor' => 4400, 'proximo_reajuste_em' => '2027-11-10'])
            ->assertNotified('Reajuste registrado');

        $this->assertEquals(4400.0, (float) $modelo->refresh()->valor);
        $this->assertSame('2027-11-10', $modelo->proximo_reajuste_em->toDateString());
        $this->assertEquals(4400.0, (float) $modelo->contas()->whereDate('vencimento', '2026-11-05')->value('valor'));
    }

    public function test_reajuste_com_data_passada_e_recusado(): void
    {
        $modelo = $this->recorrente();

        Livewire::actingAs($this->como($this->financeiro))
            ->test(EditContaRecorrente::class, ['record' => $modelo->getKey()])
            ->callAction('reajustar', ['valor' => 4400, 'proximo_reajuste_em' => '2026-10-01'])
            ->assertHasActionErrors();

        $this->assertEquals(4000.0, (float) $modelo->refresh()->valor);
    }

    public function test_despesa_inativa_nao_oferece_reajuste(): void
    {
        $modelo = $this->recorrente(['is_active' => false]);

        Livewire::actingAs($this->como($this->financeiro))
            ->test(EditContaRecorrente::class, ['record' => $modelo->getKey()])
            ->assertActionHidden('reajustar');
    }

    // --- Conta a pagar -----------------------------------------------------------------------------------------------

    private function contaEstimada(): ContaPagar
    {
        $modelo = $this->recorrente(['descricao' => 'Energia elétrica', 'valor_estimado' => true, 'valor' => 800, 'dia_vencimento' => 28]);
        app(ContaRecorrenteService::class)->gerar();

        return $modelo->contas()->whereDate('vencimento', '2026-10-28')->firstOrFail();
    }

    public function test_revisar_o_valor_aparece_so_nas_contas_de_valor_estimado_sem_revisao(): void
    {
        $estimada = $this->contaEstimada();
        $comum = ContaPagar::create(['descricao' => 'Comum', 'valor' => 100, 'vencimento' => '2026-10-30', 'status' => 'pendente']);

        $tela = Livewire::actingAs($this->como($this->financeiro))->test(ListContaPagars::class);

        $tela->assertActionVisible(TestAction::make('revisar_valor')->table($estimada))
            ->assertActionHidden(TestAction::make('revisar_valor')->table($comum));

        $tela->callAction(TestAction::make('revisar_valor')->table($estimada), ['valor' => 912.4, 'aplicar_ao_modelo' => true])->assertNotified('Valor revisado');

        $estimada->refresh();
        $this->assertEquals(912.4, (float) $estimada->valor);
        $this->assertTrue($estimada->valor_revisado);
        $this->assertEquals(912.4, (float) $estimada->contaRecorrente()->value('valor'));

        $tela->assertActionHidden(TestAction::make('revisar_valor')->table($estimada));
    }

    public function test_nao_da_baixa_em_conta_de_valor_estimado_sem_revisar(): void
    {
        $estimada = $this->contaEstimada();
        $banco = Banco::create(['nome' => 'Banco Teste', 'is_active' => true]);

        $tela = Livewire::actingAs($this->como($this->financeiro))->test(ListContaPagars::class);

        $tela->callAction(TestAction::make('dar_baixa')->table($estimada), ['banco_id' => $banco->id, 'data_pagamento' => '2026-10-27'])->assertNotified('Revise o valor antes de pagar');
        $this->assertSame(StatusContaPagar::Pendente, $estimada->refresh()->status);
        $this->assertSame(0, TransacaoBancaria::query()->count());

        $tela->callAction(TestAction::make('revisar_valor')->table($estimada), ['valor' => 850]);
        $tela->callAction(TestAction::make('dar_baixa')->table($estimada), ['banco_id' => $banco->id, 'data_pagamento' => '2026-10-27'])->assertNotified('Baixa registrada com sucesso!');

        $this->assertSame(StatusContaPagar::Pago, $estimada->refresh()->status);
        $this->assertEquals(850.0, (float) TransacaoBancaria::query()->firstOrFail()->valor);
    }

    // --- Fluxo projetado ---------------------------------------------------------------------------------------------

    public function test_pagina_do_fluxo_exige_permissao(): void
    {
        $this->actingAs($this->como($this->leitor))->get(FluxoProjetado::getUrl())->assertForbidden();
    }

    public function test_pagina_do_fluxo_mostra_o_aviso_de_saldo_negativo_e_troca_de_cenario(): void
    {
        $banco = Banco::create(['nome' => 'Banco Teste', 'is_active' => true]);
        TransacaoBancaria::create(['banco_id' => $banco->id, 'tipo' => 'entrada', 'valor' => 1000, 'data_transacao' => '2026-10-20', 'descricao' => 'Saldo', 'conciliado' => false]);
        ContaPagar::create(['descricao' => 'Fornecedor', 'valor' => 5000, 'vencimento' => '2026-11-10', 'status' => 'pendente']);

        Livewire::actingAs($this->como($this->financeiro))
            ->test(FluxoProjetado::class)
            ->assertSee('O saldo fica negativo em 10/11/2026')
            ->assertSee('Confira a conciliação bancária')
            ->assertSee('1 transação(ões) ainda sem conciliar')
            ->set('cenario', 'otimista')
            ->assertSee('Receber tudo que está aberto')
            ->set('dias', 30)
            ->assertSee('Semana a semana');
    }

    public function test_pagina_do_fluxo_sem_risco_diz_que_o_saldo_nao_fica_negativo(): void
    {
        $banco = Banco::create(['nome' => 'Banco Teste', 'is_active' => true]);
        TransacaoBancaria::create(['banco_id' => $banco->id, 'tipo' => 'entrada', 'valor' => 9000, 'data_transacao' => '2026-10-20', 'descricao' => 'Saldo', 'conciliado' => true]);

        Livewire::actingAs($this->como($this->financeiro))
            ->test(FluxoProjetado::class)
            ->assertSee('o saldo não fica negativo nos próximos 90 dias');
    }

    // --- Minha Mesa e relatórios -------------------------------------------------------------------------------------

    public function test_fila_de_recorrentes_a_revisar_conta_a_conta_estimada_perto_do_vencimento(): void
    {
        $this->contaEstimada();
        $fila = new RecorrentesParaRevisar;

        // A rotina recupera o vencimento de 28/09 (já vencido) e gera o de 28/10.
        $this->assertSame(2, $fila->contar($this->financeiro));
        $this->assertSame(1, $fila->atrasados($this->financeiro), 'a de 28/09 está vencida e sem revisão');
        $this->assertSame('Update:ContaPagar', $fila->permissao());

        $this->travel(3)->days();
        $this->assertSame(2, $fila->atrasados($this->financeiro));

        foreach (ContaPagar::query()->whereNotNull('conta_recorrente_id')->get() as $conta) {
            app(ContaRecorrenteService::class)->revisarValor($conta, 801);
        }

        $this->assertSame(0, $fila->contar($this->financeiro));
    }

    public function test_fila_de_reajustes_avisa_com_antecedencia_e_atrasa_depois_da_data(): void
    {
        $this->recorrente(['proximo_reajuste_em' => '2026-11-20']);
        $this->recorrente(['descricao' => 'Longe', 'proximo_reajuste_em' => '2027-03-01']);
        $this->recorrente(['descricao' => 'Sem data']);
        $this->recorrente(['descricao' => 'Inativa', 'proximo_reajuste_em' => '2026-11-01', 'is_active' => false]);

        $fila = new ReajustesDeContrato;

        $this->assertSame(1, $fila->contar($this->financeiro));
        $this->assertSame(0, $fila->atrasados($this->financeiro));
        $this->assertStringContainsString('Reajuste em 20/11/2026', $fila->itens($this->financeiro, 5)[0]->detalhe);

        $this->travel(30)->days();
        $this->assertSame(1, $fila->atrasados($this->financeiro));
    }

    public function test_fila_de_caixa_negativo_e_uma_linha_so_e_fica_atrasada_perto_da_data(): void
    {
        $fila = new SaldoProjetadoNegativo;
        $this->assertSame(0, $fila->contar($this->financeiro));
        $this->assertSame([], $fila->itens($this->financeiro, 3));

        ContaPagar::create(['descricao' => 'Fornecedor', 'valor' => 5000, 'vencimento' => '2026-11-20', 'status' => 'pendente']);
        $fila = new SaldoProjetadoNegativo;
        $this->assertSame(1, $fila->contar($this->financeiro));
        $this->assertSame(0, $fila->atrasados($this->financeiro), '25 dias adiante ainda não é urgente');

        ContaPagar::create(['descricao' => 'Urgente', 'valor' => 5000, 'vencimento' => '2026-11-02', 'status' => 'pendente']);
        $fila = new SaldoProjetadoNegativo;
        $this->assertSame(1, $fila->atrasados($this->financeiro));
        $this->assertStringContainsString('02/11/2026', $fila->itens($this->financeiro, 3)[0]->titulo);
    }

    public function test_fonte_de_relatorio_entrega_as_semanas_do_cenario(): void
    {
        ContaPagar::create(['descricao' => 'Fornecedor', 'valor' => 700, 'vencimento' => '2026-10-30', 'status' => 'pendente']);

        $fonte = new FluxoProjetadoSemanal;
        $linhas = $fonte->linhas(['dias' => '30', 'cenario' => 'conservador'], 100);

        $this->assertSame('View:FluxoProjetado', $fonte->permissao());
        $this->assertCount(5, $linhas);
        $this->assertSame('Semana 1', $linhas[0]['semana']);
        $this->assertEquals(700.0, $linhas[0]['saidas']);
        $this->assertEquals(-700.0, $linhas[0]['saldo']);
        $this->assertSame('Saldo negativo', $linhas[0]['situacao']);
        $this->assertCount(2, $fonte->linhas(['dias' => '30'], 2));
    }
}

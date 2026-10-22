<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\RelatorioInadimplencia;
use App\Filament\Portal\Pages\Financeiro;
use App\Filament\Resources\Contratos\Pages\EditContrato;
use App\Filament\Resources\Faturas\Pages\ListFaturas;
use App\Models\Banco;
use App\Models\Contrato;
use App\Models\Fatura;
use App\Models\ItemFatura;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\ResponsavelFinanceiro;
use App\Models\TemplateContrato;
use App\Models\TransacaoBancaria;
use App\Models\Turma;
use App\Models\User;
use App\Services\EncargosAtrasoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

/**
 * Encargos por atraso (Onda 62): multa de mora (até 2%, uma vez) e juros simples pro rata dia sobre o saldo devedor,
 * com vencimento adiado em fim de semana e feriado, carência, parâmetros por contrato, escopo de faturas em atraso,
 * relatório de inadimplência, Portal da Família e PDF do demonstrativo de débito.
 *
 * "Hoje" é quarta-feira, 21/10/2026.
 */
class EncargosAtrasoTest extends TestCase
{
    use ProibeLazyLoading;
    use RefreshDatabase;

    private Contrato $contrato;

    private Matricula $matricula;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-21 09:00:00'));
        [$this->matricula, $this->contrato] = $this->aluno('Ana Lima');
    }

    private function servico(): EncargosAtrasoService
    {
        return app(EncargosAtrasoService::class);
    }

    /**
     * @return array{0: Matricula, 1: Contrato}
     */
    private function aluno(string $nome): array
    {
        $periodo = PeriodoLetivo::firstOrCreate(['nome' => '2026'], ['data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15']);
        $turma = Turma::firstOrCreate(['nome' => 'Turma A'], ['periodo_letivo_id' => $periodo->id]);
        $matricula = Matricula::create(['pessoa_id' => Pessoa::create(['nome' => $nome])->id, 'turma_id' => $turma->id, 'situacao' => 'ativa']);
        $contrato = Contrato::create(['matricula_id' => $matricula->id, 'valor_total' => 12000, 'data_aceite' => '2026-01-05']);

        return [$matricula, $contrato];
    }

    private function fatura(string $vencimento, float $valor = 1000.0, string $status = 'pendente', ?Contrato $contrato = null, string $descricao = 'Mensalidade'): Fatura
    {
        $fatura = Fatura::create(['contrato_id' => ($contrato ?? $this->contrato)->id, 'vencimento' => $vencimento, 'status' => $status]);
        ItemFatura::create(['fatura_id' => $fatura->id, 'descricao' => $descricao, 'quantidade' => 1, 'valor_unitario' => $valor, 'desconto' => 0, 'tipo_desconto' => 'absoluto']);

        return $fatura->fresh();
    }

    private function pagamento(Fatura $fatura, float $valor): void
    {
        $banco = Banco::first() ?? Banco::create(['nome' => 'Banco Teste', 'is_active' => true]);

        TransacaoBancaria::create(['banco_id' => $banco->id, 'fatura_id' => $fatura->id, 'tipo' => 'entrada', 'valor' => $valor, 'data_transacao' => '2026-10-01', 'descricao' => 'Pagamento parcial', 'conciliado' => true]);
    }

    // --- Cálculo -----------------------------------------------------------------------------------------------------

    public function test_multa_de_2_por_cento_uma_vez_e_juros_simples_pelos_dias_de_atraso(): void
    {
        $fatura = $this->fatura('2026-10-09'); // sexta-feira: 12 dias de atraso

        $c = $this->servico()->calcular($fatura);

        $this->assertSame(12, $c['dias_atraso']);
        $this->assertTrue($c['em_atraso']);
        $this->assertSame(1000.0, $c['principal']);
        $this->assertSame(20.0, $c['multa']);
        $this->assertSame(4.0, $c['juros']); // 1000 x 1% x 12/30
        $this->assertSame(1024.0, $c['total']);
    }

    public function test_fatura_a_vencer_ou_vencendo_hoje_nao_tem_encargos(): void
    {
        foreach (['2026-10-21', '2026-10-30'] as $vencimento) {
            $c = $this->servico()->calcular($this->fatura($vencimento));

            $this->assertFalse($c['em_atraso'], $vencimento);
            $this->assertSame(0.0, $c['multa']);
            $this->assertSame(0.0, $c['juros']);
            $this->assertSame(1000.0, $c['total']);
        }
    }

    public function test_um_dia_depois_do_vencimento_ja_cobra_a_multa(): void
    {
        $c = $this->servico()->calcular($this->fatura('2026-10-20'));

        $this->assertSame(1, $c['dias_atraso']);
        $this->assertSame(20.0, $c['multa']);
        $this->assertSame(0.33, $c['juros']); // 1000 x 1% x 1/30 = 0,3333
    }

    public function test_vencimento_em_fim_de_semana_vale_no_proximo_dia_util(): void
    {
        $fatura = $this->fatura('2026-10-17'); // sábado: vale na segunda, 19/10

        $c = $this->servico()->calcular($fatura);

        $this->assertSame('2026-10-19', $c['vencimento_efetivo']->toDateString());
        $this->assertSame(2, $c['dias_atraso']);
        $this->assertSame(0.67, $c['juros']);

        $domingo = $this->servico()->calcular($fatura, Carbon::parse('2026-10-18'));

        $this->assertFalse($domingo['em_atraso'], 'no domingo ainda dá para pagar sem encargos (vale até segunda)');
        $this->assertSame(0.0, $domingo['multa']);

        $segunda = $this->servico()->calcular($fatura, Carbon::parse('2026-10-19'));

        $this->assertFalse($segunda['em_atraso'], 'o próprio dia útil ainda não é atraso');
    }

    public function test_feriado_configurado_adia_o_vencimento(): void
    {
        config(['financeiro.encargos.feriados' => ['2026-10-12', '2026-10-13']]);

        $c = $this->servico()->calcular($this->fatura('2026-10-12')); // segunda feriado + terça feriado: vale quarta, 14/10

        $this->assertSame('2026-10-14', $c['vencimento_efetivo']->toDateString());
        $this->assertSame(7, $c['dias_atraso']);
    }

    public function test_carencia_de_dias_adia_a_cobranca_mas_os_juros_contam_desde_o_vencimento(): void
    {
        config(['financeiro.encargos.carencia_dias' => 5]);

        $dentro = $this->servico()->calcular($this->fatura('2026-10-16')); // 5 dias de atraso: dentro da carência
        $fora = $this->servico()->calcular($this->fatura('2026-10-15')); // 6 dias

        $this->assertTrue($dentro['em_atraso']);
        $this->assertFalse($dentro['cobra_encargos']);
        $this->assertSame(0.0, $dentro['multa']);
        $this->assertSame(0.0, $dentro['juros']);

        $this->assertTrue($fora['cobra_encargos']);
        $this->assertSame(20.0, $fora['multa']);
        $this->assertSame(2.0, $fora['juros']); // 1000 x 1% x 6/30
    }

    public function test_contrato_com_percentuais_proprios(): void
    {
        $this->contrato->update(['multa_percentual' => 1.5, 'juros_mensal_percentual' => 2]);

        $c = $this->servico()->calcular($this->fatura('2026-09-21')); // 30 dias

        $this->assertSame(15.0, $c['multa']);
        $this->assertSame(20.0, $c['juros']); // 1000 x 2% x 30/30
        $this->assertSame(1.5, $c['multa_percentual']);
    }

    public function test_multa_do_contrato_acima_de_2_por_cento_e_limitada_ao_teto_legal(): void
    {
        \DB::table('contrato')->where('id', $this->contrato->id)->update(['multa_percentual' => 10, 'juros_mensal_percentual' => 50]);

        $parametros = $this->servico()->parametros($this->contrato->fresh());

        $this->assertSame(2.0, $parametros['multa_percentual']);
        $this->assertSame(12.0, $parametros['juros_mensal_percentual'], 'juros absurdos (erro de digitação) são limitados');
    }

    public function test_padrao_da_escola_vale_quando_o_contrato_nao_define(): void
    {
        config(['financeiro.encargos.multa_percentual' => 1.0, 'financeiro.encargos.juros_mensal_percentual' => 0.5]);

        $c = $this->servico()->calcular($this->fatura('2026-09-21'));

        $this->assertSame(10.0, $c['multa']);
        $this->assertSame(5.0, $c['juros']);
    }

    public function test_pagamento_parcial_reduz_a_base_de_calculo(): void
    {
        $fatura = $this->fatura('2026-09-21');
        $this->pagamento($fatura, 400);

        $c = $this->servico()->calcular($fatura->fresh());

        $this->assertSame(600.0, $c['principal']);
        $this->assertSame(12.0, $c['multa']);
        $this->assertSame(6.0, $c['juros']);
        $this->assertSame(618.0, $c['total']);
    }

    public function test_fatura_paga_ou_cancelada_nao_tem_encargos(): void
    {
        foreach (['pago', 'cancelado'] as $status) {
            $c = $this->servico()->calcular($this->fatura('2026-08-10', status: $status));

            $this->assertFalse($c['em_atraso'], $status);
            $this->assertSame(0.0, $c['total'], $status);
        }
    }

    public function test_totais_somam_as_linhas_em_atraso_do_contrato(): void
    {
        $this->fatura('2026-09-21', 1000.0, descricao: 'Parcela 1 de 12');
        $this->fatura('2026-10-09', 1000.0, descricao: 'Parcela 2 de 12');
        $this->fatura('2026-11-10', 1000.0, descricao: 'Parcela 3 de 12'); // a vencer
        $this->fatura('2026-08-10', 1000.0, 'pago', descricao: 'Parcela 0');

        $linhas = $this->servico()->linhasDoContrato($this->contrato);
        $totais = $this->servico()->totais($linhas);

        $this->assertSame(['Parcela 1 de 12', 'Parcela 2 de 12'], $linhas->pluck('descricao')->all());
        $this->assertSame(2, $totais['faturas']);
        $this->assertSame(2000.0, $totais['principal']);
        $this->assertSame(40.0, $totais['multa']);
        $this->assertSame(14.0, $totais['juros']); // 10 + 4
        $this->assertSame(2054.0, $totais['total']);

        $todas = $this->servico()->linhasDoContrato($this->contrato, apenasEmAtraso: false);

        $this->assertCount(3, $todas, 'em aberto: as duas vencidas e a que vence em novembro');
    }

    // --- Escopo ------------------------------------------------------------------------------------------------------

    public function test_escopo_em_atraso_pega_atrasada_e_pendente_ou_parcial_vencida(): void
    {
        $atrasada = $this->fatura('2026-10-20', status: 'atrasado');
        $pendenteVencida = $this->fatura('2026-10-10');
        $parcialVencida = $this->fatura('2026-10-05', status: 'parcial');
        $this->fatura('2026-10-30'); // a vencer
        $this->fatura('2026-10-10', status: 'pago');
        $this->fatura('2026-10-10', status: 'cancelado');

        $ids = Fatura::query()->emAtraso()->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$atrasada->id, $pendenteVencida->id, $parcialVencida->id], $ids);
    }

    // --- Relatório de inadimplência ----------------------------------------------------------------------------------

    private function superAdmin(): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $user = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $user->assignRole('super_admin');
        session(['active_role' => 'super_admin']);

        return $user;
    }

    public function test_relatorio_lista_a_fatura_pendente_vencida_e_mostra_multa_e_juros(): void
    {
        $vencida = $this->fatura('2026-10-09'); // pendente, nunca marcada como atrasada
        $this->fatura('2026-11-10');
        ResponsavelFinanceiro::create(['contrato_id' => $this->contrato->id, 'pessoa_id' => Pessoa::create(['nome' => 'Carla Lima'])->id]);

        $tela = Livewire::actingAs($this->superAdmin())->test(RelatorioInadimplencia::class)->assertCanSeeTableRecords([$vencida]);

        $resumo = $tela->instance()->getResumo();

        $this->assertSame(1, $resumo['total_faturas']);
        $this->assertEqualsWithDelta(1000.0, $resumo['total_devido'], 0.001);
        $this->assertEqualsWithDelta(24.0, $resumo['total_encargos'], 0.001);
        $this->assertEqualsWithDelta(1024.0, $resumo['total_atualizado'], 0.001);
        $tela->assertSee('R$ 24,00');
    }

    // --- Contrato ----------------------------------------------------------------------------------------------------

    public function test_formulario_do_contrato_nao_aceita_multa_acima_de_2_por_cento(): void
    {
        $template = TemplateContrato::create(['nome' => 'Contrato 2026', 'conteudo' => '<p>Termos.</p>', 'is_padrao' => true]);
        $tela = Livewire::actingAs($this->superAdmin())->test(EditContrato::class, ['record' => $this->contrato->getRouteKey()]);

        $tela->fillForm(['template_contrato_id' => $template->id, 'multa_percentual' => 3])->call('save')->assertHasFormErrors(['multa_percentual']);

        $tela->fillForm(['template_contrato_id' => $template->id, 'multa_percentual' => 1.5, 'juros_mensal_percentual' => 1])->call('save')->assertHasNoFormErrors();

        $this->assertSame('1.50', (string) $this->contrato->fresh()->multa_percentual);
    }

    // --- Lista de faturas (admin) ------------------------------------------------------------------------------------

    public function test_lista_de_faturas_mostra_multa_e_juros_da_fatura_em_atraso(): void
    {
        $vencida = $this->fatura('2026-10-09');
        $emDia = $this->fatura('2026-11-10');

        Livewire::actingAs($this->superAdmin())
            ->test(ListFaturas::class)
            ->assertCanSeeTableRecords([$vencida, $emDia])
            ->assertTableColumnStateSet('encargos', 24.0, $vencida)
            ->assertTableColumnStateSet('encargos', null, $emDia);
    }

    // --- Portal da Família -------------------------------------------------------------------------------------------

    private function familiaDe(Matricula $matricula): User
    {
        Role::firstOrCreate(['name' => 'responsavel', 'guard_name' => 'web']);
        $responsavel = Pessoa::create(['nome' => 'Responsável '.uniqid()]);
        $user = User::factory()->create(['activated_at' => now(), 'email_verified_at' => now()]);
        $responsavel->users()->save($user);
        $user->assignRole('responsavel');
        $responsavel->alunos()->attach($matricula->pessoa_id);

        return $user;
    }

    public function test_portal_mostra_encargos_da_fatura_vencida_e_o_botao_do_demonstrativo(): void
    {
        $vencida = $this->fatura('2026-10-09');
        $aVencer = $this->fatura('2026-11-10');
        $familia = $this->familiaDe($this->matricula);

        Livewire::actingAs($familia)
            ->test(Financeiro::class)
            ->assertSee('Multa e juros')
            ->assertSee('12 dia(s) de atraso')
            ->assertTableColumnStateSet('encargos', 24.0, $vencida)
            ->assertTableColumnStateSet('atualizado', 1024.0, $vencida)
            ->assertTableColumnStateSet('encargos', null, $aVencer)
            ->assertTableColumnStateSet('atualizado', null, $aVencer)
            ->assertActionExists('demonstrativo_'.$this->matricula->id);
    }

    public function test_portal_sem_atraso_nao_tem_botao_de_demonstrativo(): void
    {
        $this->fatura('2026-11-10');
        $familia = $this->familiaDe($this->matricula);

        Livewire::actingAs($familia)
            ->test(Financeiro::class)
            ->assertActionDoesNotExist('demonstrativo_'.$this->matricula->id);
    }

    // --- PDF do demonstrativo ----------------------------------------------------------------------------------------

    public function test_familia_baixa_o_demonstrativo_do_proprio_aluno(): void
    {
        $this->fatura('2026-10-09');
        $familia = $this->familiaDe($this->matricula);

        $resposta = $this->actingAs($familia)->get(route('demonstrativo-debito.pdf', $this->matricula));

        $resposta->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $resposta->headers->get('content-type'));
        $this->assertSame(0, Activity::where('description', 'Demonstrativo de débito emitido.')->count(), 'a própria família não precisa de trilha');
    }

    public function test_demonstrativo_negado_para_outra_familia_visitante_e_professor(): void
    {
        $this->fatura('2026-10-09');
        [$outra] = $this->aluno('Bia Souza');
        $familiaDeOutra = $this->familiaDe($outra);

        $this->actingAs($familiaDeOutra)->get(route('demonstrativo-debito.pdf', $this->matricula))->assertForbidden();

        Role::firstOrCreate(['name' => 'professor', 'guard_name' => 'web']);
        $professor = User::factory()->create(['activated_at' => now(), 'email_verified_at' => now()]);
        $professor->assignRole('professor');
        $this->actingAs($professor)->get(route('demonstrativo-debito.pdf', $this->matricula))->assertForbidden();

        auth()->logout();
        $this->get(route('demonstrativo-debito.pdf', $this->matricula))->assertRedirect();
    }

    public function test_equipe_com_permissao_abre_o_demonstrativo_e_a_emissao_fica_no_log(): void
    {
        $this->fatura('2026-10-09');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $papel = Role::firstOrCreate(['name' => 'secretaria', 'guard_name' => 'web']);
        $papel->givePermissionTo(Permission::firstOrCreate(['name' => 'View:Fatura', 'guard_name' => 'web']));
        $secretaria = User::factory()->create(['activated_at' => now(), 'email_verified_at' => now()]);
        $secretaria->assignRole('secretaria');
        session(['active_role' => 'secretaria']);

        $this->actingAs($secretaria)->get(route('demonstrativo-debito.pdf', $this->matricula))->assertOk();

        $log = Activity::where('description', 'Demonstrativo de débito emitido.')->first();

        $this->assertNotNull($log);
        $this->assertSame(1, $log->properties['faturas']);
        $this->assertStringNotContainsString('1024', json_encode($log->properties));
    }

    public function test_demonstrativo_de_matricula_sem_contrato_da_404(): void
    {
        $periodo = PeriodoLetivo::first();
        $turma = Turma::firstOrFail();
        $semContrato = Matricula::create(['pessoa_id' => Pessoa::create(['nome' => 'Sem Contrato'])->id, 'turma_id' => $turma->id, 'situacao' => 'ativa']);
        $familia = $this->familiaDe($semContrato);

        $this->assertNotNull($periodo);
        $this->actingAs($familia)->get(route('demonstrativo-debito.pdf', $semContrato))->assertNotFound();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PeriodicidadeContaRecorrente;
use App\Enums\StatusContaPagar;
use App\Models\Banco;
use App\Models\ContaPagar;
use App\Models\ContaRecorrente;
use App\Models\Contrato;
use App\Models\Fatura;
use App\Models\ItemFatura;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\TransacaoBancaria;
use App\Models\Turma;
use App\Services\ContaRecorrenteService;
use App\Services\FluxoProjetadoService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

/**
 * Contas recorrentes e fluxo de caixa projetado (Onda 78): geração sem duplicar, valor estimado, reajuste de contrato, e a projeção
 * dia a dia nos três cenários.
 *
 * "Hoje" é segunda-feira, 26/10/2026.
 */
class ContasRecorrentesFluxoTest extends TestCase
{
    use ProibeLazyLoading;
    use RefreshDatabase;

    private Contrato $contrato;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-26 09:00:00'));
        Cache::flush();

        $periodo = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15']);
        $turma = Turma::create(['nome' => 'Turma A', 'periodo_letivo_id' => $periodo->id]);
        $matricula = Matricula::create(['pessoa_id' => Pessoa::create(['nome' => 'Ana Lima'])->id, 'turma_id' => $turma->id, 'situacao' => 'ativa']);
        $this->contrato = Contrato::create(['matricula_id' => $matricula->id, 'valor_total' => 12000, 'data_aceite' => '2026-01-05']);
    }

    private function servico(): ContaRecorrenteService
    {
        return app(ContaRecorrenteService::class);
    }

    private function projecao(): FluxoProjetadoService
    {
        return app(FluxoProjetadoService::class);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function recorrente(array $atributos = []): ContaRecorrente
    {
        return ContaRecorrente::create($atributos + [
            'descricao' => 'Aluguel do prédio',
            'periodicidade' => 'mensal',
            'dia_vencimento' => 5,
            'valor' => 4000,
            'vigencia_inicio' => '2026-01-01',
            'antecedencia_dias' => 10,
        ])->refresh();
    }

    private function fatura(string $vencimento, float $valor, string $status = 'pendente'): Fatura
    {
        $fatura = Fatura::create(['contrato_id' => $this->contrato->id, 'vencimento' => $vencimento, 'status' => $status]);
        ItemFatura::create(['fatura_id' => $fatura->id, 'descricao' => 'Mensalidade', 'quantidade' => 1, 'valor_unitario' => $valor, 'desconto' => 0, 'tipo_desconto' => 'absoluto']);

        return $fatura->fresh();
    }

    private function transacao(string $tipo, float $valor, string $data, ?Fatura $fatura = null, bool $conciliado = true): TransacaoBancaria
    {
        $banco = Banco::first() ?? Banco::create(['nome' => 'Banco Teste', 'is_active' => true]);

        return TransacaoBancaria::create(['banco_id' => $banco->id, 'fatura_id' => $fatura?->id, 'tipo' => $tipo, 'valor' => $valor, 'data_transacao' => $data, 'descricao' => 'Teste', 'conciliado' => $conciliado]);
    }

    // --- Vencimentos -------------------------------------------------------------------------------------------------

    public function test_dia_31_em_mes_curto_vence_no_ultimo_dia_do_mes(): void
    {
        $modelo = $this->recorrente(['dia_vencimento' => 31, 'vigencia_inicio' => '2026-01-01']);

        $datas = array_map(fn (CarbonImmutable $d): string => $d->toDateString(), $this->servico()->vencimentos($modelo, Carbon::parse('2026-01-01'), Carbon::parse('2026-04-30')));

        $this->assertSame(['2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30'], $datas);
    }

    public function test_periodicidade_pula_os_meses_e_a_vigencia_corta_o_fim(): void
    {
        $bimestral = $this->recorrente(['periodicidade' => 'bimestral', 'dia_vencimento' => 10, 'vigencia_inicio' => '2026-01-01']);
        $datas = array_map(fn (CarbonImmutable $d): string => $d->toDateString(), $this->servico()->vencimentos($bimestral, Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31')));
        $this->assertSame(['2026-01-10', '2026-03-10', '2026-05-10', '2026-07-10', '2026-09-10', '2026-11-10'], $datas);

        $limitado = $this->recorrente(['vigencia_inicio' => '2026-01-01', 'vigencia_fim' => '2026-03-31']);
        $this->assertCount(3, $this->servico()->vencimentos($limitado, Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31')));
    }

    public function test_o_primeiro_vencimento_e_o_primeiro_dia_depois_do_inicio_da_vigencia(): void
    {
        $modelo = $this->recorrente(['dia_vencimento' => 5, 'vigencia_inicio' => '2026-03-20']);

        $primeiro = $this->servico()->vencimentos($modelo, Carbon::parse('2026-01-01'), Carbon::parse('2026-06-30'))[0];

        $this->assertSame('2026-04-05', $primeiro->toDateString());
        $this->assertSame(PeriodicidadeContaRecorrente::Mensal, $modelo->periodicidade);
    }

    // --- Geração -----------------------------------------------------------------------------------------------------

    public function test_gera_as_contas_da_janela_e_recupera_o_vencimento_recente_como_atrasado(): void
    {
        $modelo = $this->recorrente();

        $resultado = $this->servico()->gerar();

        // Hoje 26/10: o 05/10 (21 dias atrás, dentro dos 31 de recuperação) e o 05/11 (dentro dos 10 dias de antecedência).
        $this->assertSame(['geradas' => 2, 'ja_existiam' => 0], $resultado);

        $contas = $modelo->contas()->orderBy('vencimento')->get();
        $this->assertSame(['2026-10-05', '2026-11-05'], $contas->map(fn (ContaPagar $c): string => $c->vencimento->toDateString())->all());
        $this->assertSame([StatusContaPagar::Atrasado, StatusContaPagar::Pendente], $contas->map(fn (ContaPagar $c) => $c->status)->all());
        $this->assertSame('Aluguel do prédio — 11/2026', $contas[1]->descricao);
        $this->assertSame('2026-11-01', $contas[1]->competencia->toDateString());
        $this->assertTrue($contas[1]->valor_revisado);
    }

    public function test_rodar_de_novo_nao_duplica_a_conta(): void
    {
        $this->recorrente();
        $this->servico()->gerar();

        $this->assertSame(['geradas' => 0, 'ja_existiam' => 2], $this->servico()->gerar());
        $this->assertSame(2, ContaPagar::query()->count());

        $this->travel(1)->days();
        $this->assertSame(0, $this->servico()->gerar()['geradas']);
    }

    public function test_o_banco_recusa_a_mesma_conta_duas_vezes_no_mesmo_mes(): void
    {
        $modelo = $this->recorrente();
        $dados = ['descricao' => 'Aluguel', 'valor' => 4000, 'vencimento' => '2026-11-05', 'status' => 'pendente', 'conta_recorrente_id' => $modelo->id, 'competencia' => '2026-11-01'];

        ContaPagar::create($dados);

        $this->expectException(QueryException::class);
        ContaPagar::create($dados);
    }

    public function test_valor_estimado_gera_conta_a_revisar(): void
    {
        $modelo = $this->recorrente(['descricao' => 'Energia elétrica', 'valor_estimado' => true, 'dia_vencimento' => 8]);

        $this->servico()->gerar();

        $conta = $modelo->contas()->orderBy('vencimento')->get()->last();
        $this->assertFalse($conta->valor_revisado);
        $this->assertStringContainsString('valor estimado', $conta->observacao);
    }

    public function test_modelo_inativo_fora_da_vigencia_ou_futuro_nao_gera(): void
    {
        $this->recorrente(['is_active' => false]);
        $this->recorrente(['vigencia_fim' => '2026-08-31']);
        $this->recorrente(['vigencia_inicio' => '2027-03-01']);

        $this->assertSame(['geradas' => 0, 'ja_existiam' => 0], $this->servico()->gerar());
    }

    public function test_modelo_novo_com_vigencia_antiga_nao_gera_meses_passados(): void
    {
        $this->recorrente(['vigencia_inicio' => '2020-01-01']);

        $this->servico()->gerar();

        $this->assertSame(2, ContaPagar::query()->count());
    }

    public function test_comando_gera_as_contas(): void
    {
        $this->recorrente();

        $this->artisan('financeiro:gerar-contas-recorrentes')->expectsOutputToContain('2 conta(s) a pagar gerada(s)')->assertSuccessful();

        $this->assertSame(2, ContaPagar::query()->count());
    }

    // --- Revisão e reajuste ------------------------------------------------------------------------------------------

    public function test_revisar_o_valor_confirma_e_pode_levar_o_valor_ao_modelo(): void
    {
        $modelo = $this->recorrente(['descricao' => 'Água', 'valor_estimado' => true, 'valor' => 300]);
        $this->servico()->gerar();
        $conta = $modelo->contas()->orderBy('vencimento')->get()->last();

        $this->servico()->revisarValor($conta, 342.17, false);
        $this->assertEquals(342.17, (float) $conta->refresh()->valor);
        $this->assertTrue($conta->valor_revisado);
        $this->assertEquals(300.0, (float) $modelo->refresh()->valor);

        $this->servico()->revisarValor($conta, 350, true);
        $this->assertEquals(350.0, (float) $modelo->refresh()->valor);
    }

    public function test_conta_paga_ou_valor_zero_nao_se_revisa(): void
    {
        $modelo = $this->recorrente(['valor_estimado' => true]);
        $this->servico()->gerar();
        $conta = $modelo->contas()->orderBy('vencimento')->get()->last();

        try {
            $this->servico()->revisarValor($conta, 0);
            $this->fail('Valor zero deveria ser recusado.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('valor da fatura', $e->getMessage());
        }

        $conta->update(['status' => StatusContaPagar::Pago]);
        $this->expectException(\DomainException::class);
        $this->servico()->revisarValor($conta, 100);
    }

    public function test_reajuste_atualiza_so_as_contas_abertas_que_seguem_no_valor_antigo(): void
    {
        $modelo = $this->recorrente(['proximo_reajuste_em' => '2026-11-01', 'antecedencia_dias' => 100]);
        $this->servico()->gerar();

        $abertas = $modelo->contas()->where('vencimento', '>=', '2026-10-26')->orderBy('vencimento')->get();
        $this->assertGreaterThanOrEqual(3, $abertas->count());
        $editada = $abertas[1];
        $editada->update(['valor' => 4100]);

        $atualizadas = $this->servico()->registrarReajuste($modelo, 4400);

        $this->assertSame($abertas->count() - 1, $atualizadas);
        $this->assertEquals(4400.0, (float) $modelo->refresh()->valor);
        $this->assertSame('2027-11-01', $modelo->proximo_reajuste_em->toDateString());
        $this->assertEquals(4100.0, (float) $editada->refresh()->valor, 'a conta editada à mão fica como está');
        $this->assertEquals(4400.0, (float) $abertas[0]->refresh()->valor);
        $this->assertEquals(4000.0, (float) ContaPagar::query()->whereDate('vencimento', '2026-10-05')->value('valor'), 'o que já venceu não muda');
    }

    public function test_reajuste_recusa_valor_invalido_e_data_passada(): void
    {
        $modelo = $this->recorrente();

        try {
            $this->servico()->registrarReajuste($modelo, 0);
            $this->fail('Valor zero deveria ser recusado.');
        } catch (\DomainException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(\DomainException::class);
        $this->servico()->registrarReajuste($modelo, 5000, Carbon::parse('2026-10-01'));
    }

    // --- Fluxo projetado ---------------------------------------------------------------------------------------------

    /**
     * Saldo de R$ 8.000; uma fatura a vencer (R$ 1.000 em 05/11) e uma vencida (R$ 500 em 10/10); uma conta a pagar (R$ 3.000 em
     * 10/11); e o aluguel de R$ 4.000 todo dia 15 sem conta gerada ainda.
     */
    private function cenarioBase(): void
    {
        $this->transacao('entrada', 10000, '2026-10-01');
        $this->transacao('saida', 2000, '2026-10-10');
        $this->fatura('2026-11-05', 1000);
        $this->fatura('2026-10-10', 500, 'atrasado');
        ContaPagar::create(['descricao' => 'Fornecedor', 'valor' => 3000, 'vencimento' => '2026-11-10', 'status' => 'pendente']);
        $this->recorrente(['dia_vencimento' => 15, 'vigencia_inicio' => '2026-01-01', 'antecedencia_dias' => 0]);
    }

    public function test_cenario_otimista_recebe_tudo_e_acha_a_primeira_data_negativa(): void
    {
        $this->cenarioBase();

        $p = $this->projecao()->projetar(null, 90, 'otimista');

        $this->assertEquals(8000.0, $p['saldo_inicial']);
        $this->assertEquals(1500.0, $p['entradas']);
        $this->assertEquals(15000.0, $p['saidas']); // 3.000 + aluguel de 15/11, 15/12 e 15/01
        $this->assertEquals(-5500.0, $p['saldo_final']);
        $this->assertSame('2026-12-15', $p['primeira_data_negativa']?->toDateString());
        $this->assertSame('2027-01-15', $p['data_menor_saldo']->toDateString());
        $this->assertCount(13, $p['semanas']);
        $this->assertSame(['quantidade' => 3, 'valor' => 12000.0], $p['origem']['recorrentes']);
        $this->assertSame(['quantidade' => 1, 'valor' => 3000.0], $p['origem']['contas']);
        $this->assertSame(2, $p['origem']['faturas']['quantidade']);
    }

    public function test_cenario_conservador_nao_recebe_fatura_aberta(): void
    {
        $this->cenarioBase();

        $p = $this->projecao()->projetar(null, 90, 'conservador');

        $this->assertEquals(0.0, $p['entradas']);
        $this->assertEquals(-7000.0, $p['saldo_final']);
        $this->assertSame('2026-12-15', $p['primeira_data_negativa']?->toDateString());
    }

    public function test_cenario_esperado_usa_a_taxa_padrao_sem_historico_e_pesa_a_fatura_vencida(): void
    {
        $this->cenarioBase();

        $p = $this->projecao()->projetar(null, 90, 'esperado');

        $this->assertSame('padrao', $p['taxa_origem']);
        $this->assertEquals(0.85, $p['taxa']);
        // 1.000 x 0,85 + 500 x 0,85 x 0,5
        $this->assertEquals(1062.5, $p['entradas']);
    }

    public function test_taxa_historica_e_o_pago_sobre_o_faturado_dos_meses_anteriores(): void
    {
        $paga = $this->fatura('2026-09-10', 1000, 'pago');
        $this->transacao('entrada', 1000, '2026-09-12', $paga);
        $this->fatura('2026-09-20', 1000, 'atrasado');
        $this->fatura('2026-08-10', 1000, 'cancelado');

        $taxa = $this->projecao()->taxaDeRecebimento(CarbonImmutable::parse('2026-10-26'));

        $this->assertSame('historico', $taxa['origem']);
        $this->assertEquals(0.5, $taxa['taxa']);
    }

    public function test_fatura_parcialmente_paga_entra_so_pelo_restante(): void
    {
        $fatura = $this->fatura('2026-11-05', 1000, 'parcial');
        $this->transacao('entrada', 400, '2026-10-20', $fatura);

        $p = $this->projecao()->projetar(null, 30, 'otimista');

        $this->assertEquals(600.0, $p['entradas']);
        $this->assertEquals(400.0, $p['saldo_inicial']);
    }

    public function test_recorrente_que_ja_virou_conta_nao_conta_duas_vezes(): void
    {
        $this->recorrente(['dia_vencimento' => 15, 'antecedencia_dias' => 30]);
        $this->servico()->gerar();

        $p = $this->projecao()->projetar(null, 30, 'otimista');

        // As contas de 15/10 (recuperada, vencida: sai no primeiro dia) e de 15/11 já existem; nenhuma previsão repete o mês.
        $this->assertEquals(8000.0, $p['saidas']);
        $this->assertSame(0, $p['origem']['recorrentes']['quantidade']);
        $this->assertSame(2, $p['origem']['contas']['quantidade']);
    }

    public function test_conta_vencida_sai_no_primeiro_dia_e_saldo_inicial_negativo_ja_conta(): void
    {
        $this->transacao('saida', 500, '2026-10-20');
        ContaPagar::create(['descricao' => 'Atrasada', 'valor' => 200, 'vencimento' => '2026-10-01', 'status' => 'atrasado']);

        $p = $this->projecao()->projetar(null, 30, 'conservador');

        $this->assertSame('2026-10-26', $p['primeira_data_negativa']?->toDateString());
        $this->assertEquals(-700.0, $p['saldo_final']);
        $this->assertEquals(200.0, $p['semanas'][0]['saidas']);
        $this->assertTrue($p['semanas'][0]['negativa']);
    }

    public function test_saldo_base_da_configuracao_entra_no_saldo_inicial(): void
    {
        config(['financeiro.projecao.saldo_base' => 2500]);
        $this->transacao('entrada', 1000, '2026-10-01');

        $this->assertEquals(3500.0, $this->projecao()->projetar(null, 30)['saldo_inicial']);
    }

    public function test_mostra_a_situacao_da_conciliacao(): void
    {
        $this->transacao('entrada', 100, '2026-10-20', null, true);
        $this->transacao('entrada', 100, '2026-10-24', null, false);
        $this->transacao('saida', 50, '2026-10-25', null, false);

        $c = $this->projecao()->projetar(null, 30)['conciliacao'];

        $this->assertSame('2026-10-25', $c['ultimo_lancamento']->toDateString());
        $this->assertSame('2026-10-20', $c['ultima_conciliada']->toDateString());
        $this->assertSame(2, $c['nao_conciliadas']);
    }

    public function test_cenario_desconhecido_cai_no_esperado_e_janela_tem_limites(): void
    {
        $p = $this->projecao()->projetar(null, 2, 'qualquer');

        $this->assertSame('esperado', $p['cenario']);
        $this->assertSame(7, $p['dias']);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\StatusFatura;
use App\Models\Banco;
use App\Models\Contrato;
use App\Models\Fatura;
use App\Models\ItemFatura;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\TransacaoBancaria;
use App\Models\Turma;
use App\Services\BaixaFaturaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * O valor de uma fatura é sempre em centavos inteiros. Sem arredondar, o desconto percentual deixava uma sobra de
 * ponto flutuante (ou fração de centavo) e a fatura paga pelo valor cobrado ficava "Parcial", além de a régua de
 * cobrança continuar avisando por centavos.
 */
class FaturaCentavosTest extends TestCase
{
    use RefreshDatabase;

    private Banco $banco;

    private PeriodoLetivo $periodo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->banco = Banco::create(['nome' => 'Banco Teste', 'is_active' => true]);
        $this->periodo = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-01-01', 'data_fim' => '2026-12-31']);
    }

    private function criarFatura(float $valorUnitario, float $desconto = 0, string $tipoDesconto = 'percentual', int $quantidade = 1): Fatura
    {
        $aluno = Pessoa::create(['nome' => 'Aluno '.Pessoa::count()]);
        $matricula = Matricula::create(['pessoa_id' => $aluno->id, 'turma_id' => Turma::factory()->create(['periodo_letivo_id' => $this->periodo->id])->id, 'situacao' => 'ativa']);
        $contrato = Contrato::create(['matricula_id' => $matricula->id, 'valor_total' => $valorUnitario, 'data_aceite' => '2026-01-05']);
        $fatura = Fatura::create(['contrato_id' => $contrato->id, 'vencimento' => '2026-03-10', 'status' => 'pendente']);

        $this->adicionarItem($fatura, $valorUnitario, $desconto, $tipoDesconto, $quantidade);

        return $fatura->fresh();
    }

    private function adicionarItem(Fatura $fatura, float $valorUnitario, float $desconto = 0, string $tipoDesconto = 'percentual', int $quantidade = 1): void
    {
        ItemFatura::create([
            'fatura_id' => $fatura->id,
            'descricao' => 'Mensalidade',
            'quantidade' => $quantidade,
            'valor_unitario' => $valorUnitario,
            'desconto' => $desconto,
            'tipo_desconto' => $tipoDesconto,
        ]);
    }

    private function receber(Fatura $fatura, float $valor): void
    {
        app(BaixaFaturaService::class)->darBaixa($fatura, ['banco_id' => $this->banco->id, 'valor' => $valor, 'data_transacao' => '2026-03-08']);
    }

    /**
     * @return array<string, array{0: float|int|string|null, 1: float|int|string|null, 2: float|int|string|null, 3: ?string, 4: float}>
     */
    public static function totaisDoItem(): array
    {
        return [
            'resíduo de ponto flutuante (310 com 24%)' => [310.0, 1, 24, 'percentual', 235.6],
            'fração de centavo (800,02 com 30%)' => [800.02, 1, 30, 'percentual', 560.01],
            'meio centavo arredonda para cima (800,03 com 25%)' => [800.03, 1, 25, 'percentual', 600.02],
            'quantidade maior que um' => [99.9, 3, 10, 'percentual', 269.73],
            'desconto absoluto' => [500.0, 1, 50.5, 'absoluto', 449.5],
            'sem desconto' => [1000.0, 1, null, null, 1000.0],
            'valores vindos do banco como texto' => ['1250.00', '1', '12.50', 'percentual', 1093.75],
            'bolsa integral' => [800.0, 1, 100, 'percentual', 0.0],
        ];
    }

    #[DataProvider('totaisDoItem')]
    public function test_total_do_item_vem_em_centavos(float|int|string|null $valorUnitario, float|int|string|null $quantidade, float|int|string|null $desconto, ?string $tipo, float $esperado): void
    {
        $this->assertSame($esperado, ItemFatura::calcularTotalLiquido($valorUnitario, $quantidade, $desconto, $tipo));
    }

    public function test_valor_da_fatura_soma_itens_ja_arredondados(): void
    {
        $fatura = $this->criarFatura(800.02, 30);
        $this->adicionarItem($fatura, 800.02, 30);

        // 2 × 560,014 = 1.120,028 sem arredondar; cada item é cobrado em 560,01.
        $this->assertSame(1120.02, $fatura->fresh()->valor);
        $this->assertSame(1600.04, $fatura->fresh()->valor_bruto);
    }

    #[DataProvider('faturasComSobra')]
    public function test_fatura_paga_pelo_valor_cobrado_fica_paga_e_sem_saldo(float $preco, float $bolsa, float $pago): void
    {
        $fatura = $this->criarFatura($preco, $bolsa);

        $this->assertSame($pago, $fatura->valor);

        $this->receber($fatura, $pago);

        $fatura = $fatura->fresh();
        $this->assertSame(0.0, $fatura->valor_restante);
        $this->assertSame(StatusFatura::Pago, $fatura->status);
    }

    /**
     * @return array<string, array{0: float, 1: float, 2: float}>
     */
    public static function faturasComSobra(): array
    {
        return [
            'resíduo de ponto flutuante' => [310.0, 24, 235.6],
            'fração de centavo' => [800.02, 30, 560.01],
            'mensalidade redonda' => [1200.0, 15, 1020.0],
        ];
    }

    public function test_pagamento_parcial_continua_parcial_com_o_saldo_exato(): void
    {
        $fatura = $this->criarFatura(310.0, 24);

        $this->receber($fatura, 100.0);

        $fatura = $fatura->fresh();
        $this->assertSame(135.6, $fatura->valor_restante);
        $this->assertSame(StatusFatura::Parcial, $fatura->status);
    }

    public function test_pagamento_a_maior_quita_a_fatura(): void
    {
        $fatura = $this->criarFatura(310.0, 24);

        $this->receber($fatura, 300.0);

        $fatura = $fatura->fresh();
        $this->assertSame(-64.4, $fatura->valor_restante);
        $this->assertSame(StatusFatura::Pago, $fatura->status);
    }

    public function test_comando_lista_sem_gravar_e_com_aplicar_marca_so_as_faturas_realmente_quitadas(): void
    {
        // Estado herdado do cálculo antigo: pagamento integral gravado, status preso em Parcial.
        $quitada = $this->criarFatura(310.0, 24);
        TransacaoBancaria::create(['fatura_id' => $quitada->id, 'banco_id' => $this->banco->id, 'valor' => 235.60, 'tipo' => 'entrada', 'data_transacao' => '2026-03-08']);
        $quitada->update(['status' => StatusFatura::Parcial]);

        $comSaldo = $this->criarFatura(500.0);
        $this->receber($comSaldo, 200.0);

        $semRecebimento = $this->criarFatura(500.0);
        $semRecebimento->update(['status' => StatusFatura::Parcial]);

        $this->artisan('faturas:corrigir-quitadas')
            ->expectsOutputToContain('SIMULAÇÃO')
            ->expectsOutputToContain('Quitadas: 1')
            ->assertSuccessful();

        $this->assertSame(StatusFatura::Parcial, $quitada->fresh()->status);

        $this->artisan('faturas:corrigir-quitadas', ['--aplicar' => true, '--force' => true])->assertSuccessful();

        $this->assertSame(StatusFatura::Pago, $quitada->fresh()->status);
        $this->assertSame(StatusFatura::Parcial, $comSaldo->fresh()->status);
        $this->assertSame(StatusFatura::Parcial, $semRecebimento->fresh()->status);
    }
}

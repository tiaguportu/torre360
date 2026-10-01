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
use App\Services\ConciliacaoBancariaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConciliacaoCreditoFaturaTest extends TestCase
{
    use RefreshDatabase;

    private Banco $banco;

    protected function setUp(): void
    {
        parent::setUp();

        $this->banco = Banco::create(['nome' => 'Banco Teste', 'is_active' => true]);
    }

    private function criarFatura(float $valor, string $vencimento, ?int $id = null): Fatura
    {
        $periodo = PeriodoLetivo::create(['nome' => '2026 '.uniqid(), 'data_inicio' => '2026-01-01', 'data_fim' => '2026-12-31']);
        $aluno = Pessoa::create(['nome' => 'Aluno Teste Conciliacao']);
        $matricula = Matricula::create(['pessoa_id' => $aluno->id, 'periodo_letivo_id' => $periodo->id, 'situacao' => 'ativa']);
        $contrato = Contrato::create(['matricula_id' => $matricula->id, 'valor_total' => $valor, 'data_aceite' => '2026-01-05']);
        $fatura = Fatura::create(['contrato_id' => $contrato->id, 'vencimento' => $vencimento, 'status' => 'pendente']);

        ItemFatura::create([
            'fatura_id' => $fatura->id,
            'descricao' => 'Mensalidade',
            'quantidade' => 1,
            'valor_unitario' => $valor,
            'desconto' => 0,
        ]);

        return $fatura->fresh();
    }

    public function test_concilia_por_valor_e_data_quando_candidata_e_unica(): void
    {
        $fatura = $this->criarFatura(500.0, '2026-03-10');

        TransacaoBancaria::create([
            'banco_id' => $this->banco->id,
            'tipo' => 'entrada',
            'valor' => 500.0,
            'data_transacao' => '2026-03-09',
            'descricao' => 'PIX recebido',
            'conciliado' => false,
        ]);

        $total = app(ConciliacaoBancariaService::class)->conciliarCreditosComFaturas();

        $this->assertSame(1, $total);
        $this->assertSame(StatusFatura::Pago, $fatura->fresh()->status);
        $this->assertDatabaseHas('transacao_bancarias', [
            'fatura_id' => $fatura->id,
            'conciliado' => true,
        ]);
    }

    public function test_concilia_por_identificador_na_descricao(): void
    {
        $fatura = $this->criarFatura(500.0, '2026-03-10');
        // Outra fatura do mesmo valor e período próximo, para tornar o valor+data ambíguo
        $this->criarFatura(500.0, '2026-03-12');

        TransacaoBancaria::create([
            'banco_id' => $this->banco->id,
            'tipo' => 'entrada',
            'valor' => 500.0,
            'data_transacao' => '2026-03-09',
            'descricao' => "Pagamento Fatura #{$fatura->id}",
            'conciliado' => false,
        ]);

        $total = app(ConciliacaoBancariaService::class)->conciliarCreditosComFaturas();

        $this->assertSame(1, $total);
        $this->assertSame(StatusFatura::Pago, $fatura->fresh()->status);
    }

    public function test_nao_concilia_quando_ambiguo(): void
    {
        $this->criarFatura(500.0, '2026-03-10');
        $this->criarFatura(500.0, '2026-03-12');

        TransacaoBancaria::create([
            'banco_id' => $this->banco->id,
            'tipo' => 'entrada',
            'valor' => 500.0,
            'data_transacao' => '2026-03-09',
            'descricao' => 'PIX recebido sem referência',
            'conciliado' => false,
        ]);

        $total = app(ConciliacaoBancariaService::class)->conciliarCreditosComFaturas();

        $this->assertSame(0, $total);
        $this->assertDatabaseHas('transacao_bancarias', ['conciliado' => false]);
    }

    public function test_nao_concilia_saida(): void
    {
        $this->criarFatura(500.0, '2026-03-10');

        TransacaoBancaria::create([
            'banco_id' => $this->banco->id,
            'tipo' => 'saida',
            'valor' => 500.0,
            'data_transacao' => '2026-03-09',
            'descricao' => 'Pagamento a fornecedor',
            'conciliado' => false,
        ]);

        $total = app(ConciliacaoBancariaService::class)->conciliarCreditosComFaturas();

        $this->assertSame(0, $total);
    }

    public function test_nao_concilia_fatura_fora_da_janela_de_data(): void
    {
        $this->criarFatura(500.0, '2026-03-10');

        TransacaoBancaria::create([
            'banco_id' => $this->banco->id,
            'tipo' => 'entrada',
            'valor' => 500.0,
            'data_transacao' => '2026-06-01', // muito depois do vencimento, fora da janela
            'descricao' => 'PIX recebido',
            'conciliado' => false,
        ]);

        $total = app(ConciliacaoBancariaService::class)->conciliarCreditosComFaturas();

        $this->assertSame(0, $total);
    }

    public function test_ja_conciliadas_nao_sao_reprocessadas(): void
    {
        $fatura = $this->criarFatura(500.0, '2026-03-10');

        TransacaoBancaria::create([
            'banco_id' => $this->banco->id,
            'fatura_id' => $fatura->id,
            'tipo' => 'entrada',
            'valor' => 500.0,
            'data_transacao' => '2026-03-09',
            'conciliado' => true,
        ]);

        $total = app(ConciliacaoBancariaService::class)->conciliarCreditosComFaturas();

        $this->assertSame(0, $total);
    }
}

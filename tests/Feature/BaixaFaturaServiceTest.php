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
use App\Services\BaixaFaturaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BaixaFaturaServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Banco::create(['nome' => 'Banco Teste', 'is_active' => true]);
    }

    private function criarFatura(float $valor = 500.0): Fatura
    {
        $periodo = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-01-01', 'data_fim' => '2026-12-31']);
        $aluno = Pessoa::create(['nome' => 'Aluno Teste Baixa']);
        $matricula = Matricula::create(['pessoa_id' => $aluno->id, 'periodo_letivo_id' => $periodo->id, 'situacao' => 'ativa']);
        $contrato = Contrato::create(['matricula_id' => $matricula->id, 'valor_total' => $valor, 'data_aceite' => '2026-01-05']);
        $fatura = Fatura::create(['contrato_id' => $contrato->id, 'vencimento' => '2026-03-10', 'status' => 'pendente']);

        ItemFatura::create([
            'fatura_id' => $fatura->id,
            'descricao' => 'Mensalidade',
            'quantidade' => 1,
            'valor_unitario' => $valor,
            'desconto' => 0,
        ]);

        return $fatura->fresh();
    }

    public function test_baixa_total_marca_fatura_como_paga(): void
    {
        $fatura = $this->criarFatura(500.0);

        $transacao = app(BaixaFaturaService::class)->darBaixa($fatura, [
            'valor' => 500.0,
            'data_transacao' => '2026-03-08',
            'descricao' => 'Pago via PIX',
        ]);

        $this->assertSame('entrada', $transacao->tipo);
        $this->assertTrue((bool) $transacao->conciliado);
        $this->assertSame(StatusFatura::Pago, $fatura->fresh()->status);
        $this->assertEquals(0, $fatura->fresh()->valor_restante);
    }

    public function test_baixa_parcial_marca_fatura_como_parcial(): void
    {
        $fatura = $this->criarFatura(500.0);

        app(BaixaFaturaService::class)->darBaixa($fatura, [
            'valor' => 200.0,
            'data_transacao' => '2026-03-08',
        ]);

        $this->assertSame(StatusFatura::Parcial, $fatura->fresh()->status);
        $this->assertEquals(300.0, $fatura->fresh()->valor_restante);
    }

    public function test_baixas_sucessivas_completam_o_pagamento(): void
    {
        $fatura = $this->criarFatura(500.0);
        $service = app(BaixaFaturaService::class);

        $service->darBaixa($fatura, ['valor' => 200.0, 'data_transacao' => '2026-03-01']);
        $this->assertSame(StatusFatura::Parcial, $fatura->fresh()->status);

        $service->darBaixa($fatura, ['valor' => 300.0, 'data_transacao' => '2026-03-08']);
        $this->assertSame(StatusFatura::Pago, $fatura->fresh()->status);
    }

    public function test_baixa_nao_reabre_fatura_cancelada(): void
    {
        $fatura = $this->criarFatura(500.0);
        $fatura->update(['status' => StatusFatura::Cancelado]);

        app(BaixaFaturaService::class)->darBaixa($fatura, [
            'valor' => 500.0,
            'data_transacao' => '2026-03-08',
        ]);

        $this->assertSame(StatusFatura::Cancelado, $fatura->fresh()->status);
    }

    public function test_conciliado_pode_ser_marcado_como_falso(): void
    {
        $fatura = $this->criarFatura(500.0);

        $transacao = app(BaixaFaturaService::class)->darBaixa($fatura, [
            'valor' => 500.0,
            'data_transacao' => '2026-03-08',
            'conciliado' => false,
        ]);

        $this->assertFalse((bool) $transacao->fresh()->conciliado);
    }

    public function test_usa_banco_configurado_como_padrao_quando_nao_informado(): void
    {
        $bancoConfigurado = Banco::create(['nome' => 'Banco da Conta Gateway', 'is_active' => true]);
        config(['pagamentos.banco_id_padrao' => $bancoConfigurado->id]);

        $fatura = $this->criarFatura(500.0);

        $transacao = app(BaixaFaturaService::class)->darBaixa($fatura, [
            'valor' => 500.0,
            'data_transacao' => '2026-03-08',
        ]);

        $this->assertSame($bancoConfigurado->id, $transacao->banco_id);
    }

    public function test_lanca_excecao_quando_nao_ha_banco_informado_nem_ativo(): void
    {
        Banco::query()->delete();
        $fatura = $this->criarFatura(500.0);

        $this->expectException(\RuntimeException::class);
        app(BaixaFaturaService::class)->darBaixa($fatura, [
            'valor' => 500.0,
            'data_transacao' => '2026-03-08',
        ]);
    }
}

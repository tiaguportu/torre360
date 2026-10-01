<?php

namespace Tests\Feature;

use App\Contracts\GatewayPagamento;
use App\Enums\StatusFatura;
use App\Models\Banco;
use App\Models\Contrato;
use App\Models\Fatura;
use App\Models\ItemFatura;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\TransacaoBancaria;
use App\Services\GatewayPagamentoManager;
use App\Services\PagamentoConfirmacaoService;
use App\Services\ReguaCobrancaService;
use App\Services\WebhookSignatureValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class GatewayPagamentoTest extends TestCase
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
        $aluno = Pessoa::create(['nome' => 'Aluno Teste Gateway']);
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

    public function test_manager_resolve_driver_configurado(): void
    {
        $gateway = GatewayPagamentoManager::resolver();

        $this->assertInstanceOf(GatewayPagamento::class, $gateway);
        $this->assertSame('fake', $gateway->chave());
    }

    public function test_manager_lanca_excecao_para_driver_desconhecido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GatewayPagamentoManager::resolver('inexistente');
    }

    public function test_manager_lista_opcoes(): void
    {
        $opcoes = GatewayPagamentoManager::opcoes();

        $this->assertArrayHasKey('fake', $opcoes);
    }

    public function test_driver_fake_gera_dados_de_cobranca(): void
    {
        $fatura = $this->criarFatura(500.0);

        $dados = GatewayPagamentoManager::resolver('fake')->criarCobranca($fatura);

        $this->assertSame('fake', $dados['gateway']);
        $this->assertStringStartsWith('FAKE-'.$fatura->id.'-', $dados['gateway_id']);
        $this->assertNotEmpty($dados['pix_copia_e_cola']);
        $this->assertNotEmpty($dados['linha_digitavel']);
        $this->assertSame('aguardando_pagamento', $dados['status_gateway']);
    }

    public function test_gerar_cobranca_persiste_dados_na_fatura(): void
    {
        $fatura = $this->criarFatura(500.0);

        $dados = GatewayPagamentoManager::resolver()->criarCobranca($fatura);
        $fatura->update($dados);

        $fatura = $fatura->fresh();
        $this->assertSame('fake', $fatura->gateway);
        $this->assertNotNull($fatura->gateway_id);
        $this->assertNotNull($fatura->pix_copia_e_cola);
    }

    public function test_confirmar_pagamento_gera_baixa_e_marca_fatura_paga(): void
    {
        $fatura = $this->criarFatura(500.0);
        $dados = GatewayPagamentoManager::resolver()->criarCobranca($fatura);
        $fatura->update($dados);

        $resultado = app(PagamentoConfirmacaoService::class)->confirmar(
            $fatura->fresh(),
            500.0,
            '2026-03-05',
            'evt_1'
        );

        $this->assertTrue($resultado['processado']);
        $this->assertSame(StatusFatura::Pago, $fatura->fresh()->status);
        $this->assertSame('pago', $fatura->fresh()->status_gateway);
        $this->assertDatabaseHas('transacao_bancarias', [
            'fatura_id' => $fatura->id,
            'external_id' => 'evt_1',
            'conciliado' => true,
        ]);
    }

    public function test_confirmar_pagamento_e_idempotente_por_event_id(): void
    {
        $fatura = $this->criarFatura(500.0);
        $dados = GatewayPagamentoManager::resolver()->criarCobranca($fatura);
        $fatura->update($dados);

        $service = app(PagamentoConfirmacaoService::class);
        $service->confirmar($fatura->fresh(), 500.0, '2026-03-05', 'evt_repetido');
        $resultadoSegundo = $service->confirmar($fatura->fresh(), 500.0, '2026-03-05', 'evt_repetido');

        $this->assertFalse($resultadoSegundo['processado']);
        $this->assertSame(1, TransacaoBancaria::where('external_id', 'evt_repetido')->count());
    }

    public function test_confirmar_pagamento_ignora_fatura_cancelada(): void
    {
        $fatura = $this->criarFatura(500.0);
        $fatura->update(['status' => StatusFatura::Cancelado]);

        $resultado = app(PagamentoConfirmacaoService::class)->confirmar($fatura, 500.0, '2026-03-05', 'evt_cancelada');

        $this->assertFalse($resultado['processado']);
        $this->assertSame(StatusFatura::Cancelado, $fatura->fresh()->status);
    }

    public function test_validador_de_assinatura_pula_quando_sem_segredo(): void
    {
        $validator = new WebhookSignatureValidator;

        $this->assertTrue($validator->valida(null, '{"a":1}', null));
    }

    public function test_validador_de_assinatura_rejeita_sem_header(): void
    {
        $validator = new WebhookSignatureValidator;

        $this->assertFalse($validator->valida('segredo', '{"a":1}', null));
    }

    public function test_validador_de_assinatura_aceita_assinatura_correta(): void
    {
        $validator = new WebhookSignatureValidator;
        $payload = '{"a":1}';
        $assinatura = hash_hmac('sha256', $payload, 'segredo');

        $this->assertTrue($validator->valida('segredo', $payload, $assinatura));
    }

    public function test_validador_de_assinatura_rejeita_assinatura_incorreta(): void
    {
        $validator = new WebhookSignatureValidator;

        $this->assertFalse($validator->valida('segredo', '{"a":1}', 'assinatura-errada'));
    }

    public function test_macro_pix_copia_cola_usa_dado_real_apos_gerar_cobranca(): void
    {
        $fatura = $this->criarFatura(500.0);
        $responsavel = Pessoa::create(['nome' => 'Responsável Teste']);

        $semCobranca = app(ReguaCobrancaService::class)->substituirMacros(
            '{{PIX_COPIA_COLA}}',
            $fatura,
            $responsavel,
            null,
            now()
        );
        $this->assertStringContainsString('ainda não gerado', $semCobranca);

        $dados = GatewayPagamentoManager::resolver()->criarCobranca($fatura);
        $fatura->update($dados);

        $comCobranca = app(ReguaCobrancaService::class)->substituirMacros(
            '{{PIX_COPIA_COLA}}',
            $fatura->fresh(),
            $responsavel,
            null,
            now()
        );
        $this->assertSame($dados['pix_copia_e_cola'], $comCobranca);
    }
}

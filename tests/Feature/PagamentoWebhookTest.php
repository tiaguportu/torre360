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
use App\Services\GatewayPagamentoManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagamentoWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Banco::create(['nome' => 'Banco Teste', 'is_active' => true]);
    }

    private function criarFaturaComCobranca(float $valor = 500.0): Fatura
    {
        $periodo = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-01-01', 'data_fim' => '2026-12-31']);
        $aluno = Pessoa::create(['nome' => 'Aluno Teste Webhook']);
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

        $fatura = $fatura->fresh();
        $dados = GatewayPagamentoManager::resolver()->criarCobranca($fatura);
        $fatura->update($dados);

        return $fatura->fresh();
    }

    public function test_webhook_confirma_pagamento_e_marca_fatura_paga(): void
    {
        $fatura = $this->criarFaturaComCobranca(500.0);

        $response = $this->postJson('/api/webhooks/pagamento', [
            'gateway_id' => $fatura->gateway_id,
            'event_id' => 'evt_abc123',
            'evento' => 'pagamento_confirmado',
            'valor' => 500.0,
            'data_pagamento' => '2026-03-05',
        ]);

        $response->assertOk();
        $this->assertSame(StatusFatura::Pago, $fatura->fresh()->status);
        $this->assertDatabaseHas('transacao_bancarias', [
            'fatura_id' => $fatura->id,
            'external_id' => 'evt_abc123',
        ]);
    }

    public function test_webhook_e_idempotente_em_reenvio(): void
    {
        $fatura = $this->criarFaturaComCobranca(500.0);

        $payload = [
            'gateway_id' => $fatura->gateway_id,
            'event_id' => 'evt_reenviado',
            'evento' => 'pagamento_confirmado',
            'valor' => 500.0,
            'data_pagamento' => '2026-03-05',
        ];

        $this->postJson('/api/webhooks/pagamento', $payload)->assertOk();
        $this->postJson('/api/webhooks/pagamento', $payload)->assertOk();

        $this->assertSame(1, TransacaoBancaria::where('external_id', 'evt_reenviado')->count());
    }

    public function test_webhook_retorna_200_para_gateway_id_desconhecido(): void
    {
        $response = $this->postJson('/api/webhooks/pagamento', [
            'gateway_id' => 'FAKE-INEXISTENTE',
            'evento' => 'pagamento_confirmado',
        ]);

        $response->assertOk();
    }

    public function test_webhook_rejeita_assinatura_invalida_quando_segredo_configurado(): void
    {
        config(['pagamentos.webhook_secret' => 'segredo-teste']);
        $fatura = $this->criarFaturaComCobranca(500.0);

        $response = $this->postJson('/api/webhooks/pagamento', [
            'gateway_id' => $fatura->gateway_id,
            'evento' => 'pagamento_confirmado',
        ], ['X-Pagamento-Signature' => 'assinatura-forjada']);

        $response->assertStatus(401);
        $this->assertSame(StatusFatura::Pendente, $fatura->fresh()->status);
    }

    public function test_webhook_aceita_assinatura_valida_quando_segredo_configurado(): void
    {
        config(['pagamentos.webhook_secret' => 'segredo-teste']);
        $fatura = $this->criarFaturaComCobranca(500.0);

        $payload = [
            'gateway_id' => $fatura->gateway_id,
            'event_id' => 'evt_assinado',
            'evento' => 'pagamento_confirmado',
            'valor' => 500.0,
            'data_pagamento' => '2026-03-05',
        ];

        $assinatura = hash_hmac('sha256', json_encode($payload), 'segredo-teste');

        $response = $this->call('POST', '/api/webhooks/pagamento', [], [], [], [
            'HTTP_X-Pagamento-Signature' => $assinatura,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));

        $response->assertOk();
        $this->assertSame(StatusFatura::Pago, $fatura->fresh()->status);
    }

    public function test_webhook_evento_diferente_de_pagamento_so_atualiza_status_gateway(): void
    {
        $fatura = $this->criarFaturaComCobranca(500.0);

        $this->postJson('/api/webhooks/pagamento', [
            'gateway_id' => $fatura->gateway_id,
            'evento' => 'pix_gerado',
        ])->assertOk();

        $fatura = $fatura->fresh();
        $this->assertSame('pix_gerado', $fatura->status_gateway);
        $this->assertSame(StatusFatura::Pendente, $fatura->status);
    }
}

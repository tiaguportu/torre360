<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Fatura;
use App\Services\PagamentoConfirmacaoService;
use App\Services\WebhookSignatureValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PagamentoWebhookController extends Controller
{
    /**
     * Payload esperado (contrato genérico — um driver real do gateway mapeia o formato
     * próprio dele para este mesmo formato antes de chamar, ou expõe o próprio endpoint
     * que traduz e reenvia para cá):
     * - gateway_id (string, obrigatório): id da cobrança, o mesmo gravado em Fatura.gateway_id
     * - event_id (string, opcional): id único do evento, para idempotência; usa gateway_id se ausente
     * - evento (string): "pagamento_confirmado" dispara a baixa; outros valores só atualizam status_gateway
     * - valor (float, opcional): valor pago; usa o saldo devedor da fatura se ausente
     * - data_pagamento (string, opcional): data do pagamento; usa hoje se ausente
     */
    public function __invoke(Request $request, PagamentoConfirmacaoService $service, WebhookSignatureValidator $validator)
    {
        $secret = config('pagamentos.webhook_secret');
        $assinatura = $request->header('X-Pagamento-Signature');

        if (! $validator->valida($secret, $request->getContent(), $assinatura)) {
            Log::warning('Webhook de pagamento: assinatura inválida', ['ip' => $request->ip()]);

            return response()->json(['message' => 'assinatura inválida'], 401);
        }

        if (! $secret) {
            Log::warning('Webhook de pagamento processado sem validação de assinatura — configure PAGAMENTOS_WEBHOOK_SECRET antes de ir para produção.');
        }

        $payload = $request->all();

        Log::info('Webhook de pagamento recebido', ['payload' => $payload]);

        $gatewayId = $payload['gateway_id'] ?? null;
        $eventId = $payload['event_id'] ?? $gatewayId;
        $evento = $payload['evento'] ?? null;

        if (! $gatewayId || ! $eventId) {
            Log::warning('Webhook de pagamento: payload sem gateway_id/event_id', ['payload' => $payload]);

            return response()->json(['message' => 'payload inválido'], 422);
        }

        $fatura = Fatura::where('gateway_id', $gatewayId)->first();

        if (! $fatura) {
            // 200 (não 404): evita que o provedor fique reenviando indefinidamente um
            // gateway_id que nunca vai existir no nosso lado (ex.: cobrança de teste).
            Log::warning('Webhook de pagamento: fatura não encontrada para o gateway_id', ['gateway_id' => $gatewayId]);

            return response()->json(['message' => 'fatura não encontrada'], 200);
        }

        if ($evento !== 'pagamento_confirmado') {
            if ($evento) {
                $fatura->update(['status_gateway' => $evento]);
            }

            return response()->json(['message' => 'evento registrado, sem baixa'], 200);
        }

        $valor = isset($payload['valor']) ? (float) $payload['valor'] : $fatura->valor_restante;
        $dataPagamento = $payload['data_pagamento'] ?? now()->toDateString();

        $resultado = $service->confirmar($fatura, $valor, $dataPagamento, (string) $eventId);

        return response()->json([
            'message' => $resultado['processado'] ? 'pagamento confirmado' : "ignorado: {$resultado['motivo']}",
        ], 200);
    }
}

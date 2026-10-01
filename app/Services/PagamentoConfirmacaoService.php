<?php

namespace App\Services;

use App\Enums\StatusFatura;
use App\Models\Fatura;
use App\Models\TransacaoBancaria;

/**
 * Confirma o pagamento de uma fatura vindo de um gateway — usado pelo
 * `PagamentoWebhookController` (confirmação real) e pela ação "Simular Pagamento" do
 * driver fake (mesma lógica, sem depender de um webhook de verdade chegar).
 *
 * Idempotente por `event_id`: reenvios do mesmo evento (comuns em webhooks — o provedor
 * reenvia até receber 200) não duplicam a baixa nem o lançamento bancário.
 */
class PagamentoConfirmacaoService
{
    public function __construct(private BaixaFaturaService $baixaFaturaService) {}

    /**
     * @return array{processado: bool, motivo: ?string}
     */
    public function confirmar(Fatura $fatura, float $valor, string $dataPagamento, string $eventId, ?string $descricao = null): array
    {
        if (TransacaoBancaria::where('external_id', $eventId)->exists()) {
            return ['processado' => false, 'motivo' => 'evento já processado anteriormente (idempotência)'];
        }

        if ($fatura->status === StatusFatura::Cancelado) {
            return ['processado' => false, 'motivo' => 'fatura está cancelada'];
        }

        $this->baixaFaturaService->darBaixa($fatura, [
            'valor' => $valor,
            'data_transacao' => $dataPagamento,
            'descricao' => $descricao ?? "Pagamento via gateway ({$fatura->gateway}) — Fatura #{$fatura->id}",
            'conciliado' => true,
            'external_id' => $eventId,
        ]);

        $fatura->update(['status_gateway' => 'pago']);

        return ['processado' => true, 'motivo' => null];
    }
}

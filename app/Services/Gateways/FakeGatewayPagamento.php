<?php

namespace App\Services\Gateways;

use App\Contracts\GatewayPagamento;
use App\Models\Fatura;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Driver de desenvolvimento/teste: gera dados de cobrança simulados e determinísticos,
 * sem chamar nenhuma API externa. Registra cada chamada no log, como um gateway real
 * faria em uma chamada de API. `consultar()` nunca confirma pagamento sozinho — este
 * driver depende do webhook (`/api/webhooks/pagamento`) para simular a confirmação,
 * assim como um gateway real dependeria do próprio webhook.
 */
class FakeGatewayPagamento implements GatewayPagamento
{
    public function chave(): string
    {
        return 'fake';
    }

    public function rotulo(): string
    {
        return 'Fake (desenvolvimento/teste)';
    }

    public function criarCobranca(Fatura $fatura): array
    {
        $gatewayId = 'FAKE-'.$fatura->id.'-'.Str::upper(Str::random(8));
        $valorCentavos = (int) round($fatura->valor_restante * 100);

        Log::info('[GatewayFake] Cobrança criada', [
            'fatura_id' => $fatura->id,
            'gateway_id' => $gatewayId,
            'valor' => $fatura->valor_restante,
        ]);

        return [
            'gateway' => $this->chave(),
            'gateway_id' => $gatewayId,
            'pix_copia_e_cola' => $this->gerarPixFake($gatewayId, $valorCentavos),
            'linha_digitavel' => $this->gerarLinhaDigitavelFake($valorCentavos),
            'boleto_url' => null,
            'link_pagamento' => url('/portal'),
            'status_gateway' => 'aguardando_pagamento',
        ];
    }

    public function consultar(string $gatewayId): array
    {
        Log::info('[GatewayFake] Consulta de status', ['gateway_id' => $gatewayId]);

        return ['status_gateway' => 'aguardando_pagamento', 'pago' => false];
    }

    public function cancelar(string $gatewayId): bool
    {
        Log::info('[GatewayFake] Cobrança cancelada', ['gateway_id' => $gatewayId]);

        return true;
    }

    /**
     * Monta um payload no formato EMV (BR Code) do PIX — sintaticamente válido, mas com
     * dados fictícios. Serve só para exibir algo "copiável" na tela; um driver real
     * devolve o payload assinado pelo próprio banco/PSP.
     */
    private function gerarPixFake(string $gatewayId, int $valorCentavos): string
    {
        $valorFormatado = number_format($valorCentavos / 100, 2, '.', '');

        return '00020126360014BR.GOV.BCB.PIX0114+55119999900005204000053039865'
            .'54'.strlen($valorFormatado).$valorFormatado
            .'5802BR5925TORRE360 GESTAO ESCOLAR6009SAO PAULO62'
            .str_pad((string) strlen($gatewayId), 2, '0', STR_PAD_LEFT).$gatewayId.'6304FAKE';
    }

    private function gerarLinhaDigitavelFake(int $valorCentavos): string
    {
        return '23793.38128 60007.827136 12000.063305 9 '.str_pad((string) $valorCentavos, 10, '0', STR_PAD_LEFT);
    }
}

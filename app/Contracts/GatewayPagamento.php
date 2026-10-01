<?php

namespace App\Contracts;

use App\Models\Fatura;

/**
 * Um gateway de cobrança (PIX/boleto/link de pagamento). O driver inicial é o Fake
 * (`App\Services\Gateways\FakeGatewayPagamento`), que não chama nenhuma API externa —
 * um driver real (Asaas, Efí etc.) entra depois implementando esta mesma interface e
 * sendo registrado em `GatewayPagamentoManager`, sem alterar quem a consome (Fatura,
 * `PagamentoWebhookController`, Portal da Família).
 */
interface GatewayPagamento
{
    /**
     * Identificador estável do driver (ex.: "fake", "asaas"), usado para persistir a
     * escolha em `Fatura.gateway` e resolver o driver de volta no webhook.
     */
    public function chave(): string;

    /**
     * Nome amigável exibido nas telas de configuração/administração.
     */
    public function rotulo(): string;

    /**
     * Cria a cobrança no gateway para o saldo devedor atual da fatura.
     *
     * @return array{gateway: string, gateway_id: string, pix_copia_e_cola: ?string, linha_digitavel: ?string, boleto_url: ?string, link_pagamento: ?string, status_gateway: string}
     */
    public function criarCobranca(Fatura $fatura): array;

    /**
     * Consulta o status atual de uma cobrança já criada. Usado como fallback quando o
     * webhook não chegou (verificação manual/rotina de auditoria).
     *
     * @return array{status_gateway: string, pago: bool}
     */
    public function consultar(string $gatewayId): array;

    /**
     * Cancela uma cobrança pendente no gateway (ex.: fatura foi cancelada no sistema).
     */
    public function cancelar(string $gatewayId): bool;
}

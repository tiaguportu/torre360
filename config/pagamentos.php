<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Driver de Gateway de Pagamento
    |--------------------------------------------------------------------------
    |
    | Qual implementação de App\Contracts\GatewayPagamento é usada para gerar
    | cobranças (PIX/boleto/link de pagamento) e processar confirmações. O
    | driver "fake" não chama nenhuma API externa — serve para desenvolvimento
    | e demonstração. Um driver real (Asaas, Efí etc.) entra depois trocando só
    | este valor e registrando a classe em GatewayPagamentoManager, sem mexer
    | no restante do sistema (Fatura, webhook, portal já dependem só da
    | interface).
    |
    */

    'driver' => env('PAGAMENTOS_GATEWAY_DRIVER', 'fake'),

    /*
    |--------------------------------------------------------------------------
    | Banco Padrão para Baixas Automáticas
    |--------------------------------------------------------------------------
    |
    | Usado por BaixaFaturaService quando a baixa não veio de um operador
    | escolhendo o banco na tela (webhook de gateway, conciliação bancária
    | automática). Se vazio, cai no primeiro banco ativo cadastrado.
    |
    */

    'banco_id_padrao' => env('PAGAMENTOS_BANCO_ID_PADRAO'),

    /*
    |--------------------------------------------------------------------------
    | Segredo do Webhook de Pagamento
    |--------------------------------------------------------------------------
    |
    | Usado para validar a assinatura HMAC-SHA256 (header X-Pagamento-Signature)
    | das requisições recebidas em /api/webhooks/pagamento. Se vazio, o endpoint
    | RECUSA todas as requisições (503): defina o segredo antes de habilitar um
    | gateway real. O botão "Simular Pagamento (Dev)" do driver fake não usa o
    | webhook, então não depende dele.
    |
    */

    'webhook_secret' => env('PAGAMENTOS_WEBHOOK_SECRET'),

];

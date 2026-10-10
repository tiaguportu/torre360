<?php

namespace App\Services;

/**
 * Validação de assinatura HMAC-SHA256 para webhooks (payload bruto assinado com um
 * segredo compartilhado). Reutilizada pelo webhook de pagamento e pelo webhook do
 * Assinafy — cada provedor manda a assinatura num header próprio, mas o cálculo é o
 * mesmo.
 *
 * Sem segredo configurado a assinatura não pode ser conferida, então a validação FALHA (retorna
 * false): um endpoint aberto aceitaria qualquer requisição como se viesse do provedor. Quem chama
 * decide o que fazer sem segredo (o webhook de pagamento responde 503; o do Assinafy só valida
 * quando o segredo e a assinatura existem, porque confirma o documento na API do provedor).
 */
class WebhookSignatureValidator
{
    public function valida(?string $secret, string $payloadBruto, ?string $assinaturaRecebida): bool
    {
        if (blank($secret)) {
            return false;
        }

        if (! $assinaturaRecebida) {
            return false;
        }

        $assinaturaEsperada = hash_hmac('sha256', $payloadBruto, $secret);

        return hash_equals($assinaturaEsperada, $assinaturaRecebida);
    }
}

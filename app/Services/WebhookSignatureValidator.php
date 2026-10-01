<?php

namespace App\Services;

/**
 * Validação de assinatura HMAC-SHA256 para webhooks (payload bruto assinado com um
 * segredo compartilhado). Reutilizada pelo webhook de pagamento e pelo webhook do
 * Assinafy — cada provedor manda a assinatura num header próprio, mas o cálculo é o
 * mesmo.
 *
 * Quando nenhum segredo está configurado, a validação é pulada (retorna true) para não
 * quebrar um webhook que ainda não teve o segredo configurado — quem chama deve logar um
 * aviso nesse caso, para o time perceber e configurar antes de ir para produção.
 */
class WebhookSignatureValidator
{
    public function valida(?string $secret, string $payloadBruto, ?string $assinaturaRecebida): bool
    {
        if (! $secret) {
            return true;
        }

        if (! $assinaturaRecebida) {
            return false;
        }

        $assinaturaEsperada = hash_hmac('sha256', $payloadBruto, $secret);

        return hash_equals($assinaturaEsperada, $assinaturaRecebida);
    }
}

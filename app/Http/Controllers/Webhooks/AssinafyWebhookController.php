<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\AssinafyService;
use App\Services\WebhookSignatureValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AssinafyWebhookController extends Controller
{
    public function __invoke(Request $request, AssinafyService $service, WebhookSignatureValidator $validator)
    {
        $payload = $request->all();

        Log::info('Webhook Assinafy recebido', [
            'method' => $request->method(),
            'event' => $payload['event'] ?? null,
            'document_id' => $payload['object']['id'] ?? $payload['document_id'] ?? $payload['id'] ?? null,
        ]);

        // Responde 200 para requisições vazias ou pings de validação (GET, corpo vazio) sem
        // exigir assinatura — não há payload assinável nesse caso.
        if (empty($payload)) {
            return response()->json(['message' => 'Webhook endpoint is active'], 200);
        }

        $secret = config('services.assinafy.webhook_secret');
        $assinatura = $request->header('X-Assinafy-Signature');

        if ($request->isMethod('post') && ! $validator->valida($secret, $request->getContent(), $assinatura)) {
            Log::warning('Webhook Assinafy: assinatura inválida', ['ip' => $request->ip()]);

            return response()->json(['message' => 'assinatura inválida'], 401);
        }

        if (! $secret) {
            Log::warning('Webhook Assinafy processado sem validação de assinatura — configure ASSINAFY_WEBHOOK_SECRET antes de ir para produção.');
        }

        $success = $service->handleWebhook($payload);

        if ($success) {
            return response()->json(['message' => 'Webhook processado com sucesso'], 200);
        }

        // Em webhooks, é recomendável retornar 200 mesmo que não encontre o registro interno
        // para que o serviço emissor não considere falha de rede/disponibilidade.
        Log::warning('Webhook Assinafy: Contrato não encontrado ou payload inconsistente', [
            'event' => $payload['event'] ?? null,
            'document_id' => $payload['object']['id'] ?? $payload['document_id'] ?? $payload['id'] ?? null,
        ]);

        return response()->json(['message' => 'Webhook recebido'], 200);
    }
}

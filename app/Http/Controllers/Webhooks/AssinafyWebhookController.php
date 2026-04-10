<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\AssinafyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AssinafyWebhookController extends Controller
{
    public function __invoke(Request $request, AssinafyService $service)
    {
        Log::info('Webhook Assinafy recebido', [
            'method' => $request->method(),
            'payload' => $request->all()
        ]);

        // Responde 200 para requisições vazias ou pings de validação
        if (empty($request->all())) {
            return response()->json(['message' => 'Webhook endpoint is active'], 200);
        }
        return response()->json(['message' => $request->all()], 200);
        $success = $service->handleWebhook($request->all());

        if ($success) {
            return response()->json(['message' => 'Webhook processado com sucesso'], 200);
        }

        // Em webhooks, é recomendável retornar 200 mesmo que não encontre o registro interno 
        // para que o serviço emissor não considere falha de rede/disponibilidade.
        Log::warning('Webhook Assinafy: Contrato não encontrado ou payload inconsistente', ['payload' => $request->all()]);
        return response()->json(['message' => 'Webhook recebido'], 200);
    }
}

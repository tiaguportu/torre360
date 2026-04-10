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
        Log::info('Webhook Assinafy recebido', ['payload' => $request->all()]);

        $success = $service->handleWebhook($request->all());

        if ($success) {
            return response()->json(['message' => 'Webhook processado com sucesso'], 200);
        }

        return response()->json(['message' => 'Contrato não encontrado ou payload inválido'], 404);
    }
}

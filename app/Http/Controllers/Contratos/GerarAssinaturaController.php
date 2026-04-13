<?php

namespace App\Http\Controllers\Contratos;

use App\Http\Controllers\Controller;
use App\Models\Contrato;
use App\Services\AssinafyService;

class GerarAssinaturaController extends Controller
{
    public function __invoke(Contrato $contrato, AssinafyService $service)
    {
        $result = $service->enviarContrato($contrato);

        if ($result['success'] && isset($result['redirect_url'])) {
            return redirect()->away($result['redirect_url']);
        }

        return back()->with('error', $result['message'] ?? 'Erro ao processar assinatura.');
    }
}

<?php

namespace App\Http\Controllers\Contratos;

use App\Http\Controllers\Controller;
use App\Models\Contrato;
use App\Services\AssinafyService;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;

class DownloadContratoController extends Controller
{
    public function __invoke(Contrato $contrato, AssinafyService $assinafyService)
    {
        $downloadUrl = $assinafyService->getDownloadUrl($contrato);

        if (!$downloadUrl) {
            Notification::make()
                ->title('Erro ao obter download')
                ->body('Não foi possível obter o link de download do contrato no Assinafy. O documento pode ainda não estar pronto ou o link expirou.')
                ->danger()
                ->send();

            return back();
        }

        return redirect()->away($downloadUrl);
    }
}

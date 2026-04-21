<?php

namespace App\Http\Controllers;

use App\Models\Matricula;
use App\Services\BoletimService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class BoletimPDFController extends Controller
{
    public function download(Matricula $record, Request $request)
    {
        $etapaId = $request->query('etapa_id');
        $boletimService = app(BoletimService::class);

        $dados = $boletimService->getDadosBoletim($record, $etapaId ?: null);

        if (empty($dados['etapas'])) {
            return back()->with('error', 'Não há dados para gerar o boletim solicitado.');
        }

        $pdf = Pdf::loadView('pdfs.boletim', $dados)
            ->setPaper('a4', 'portrait');

        $filename = 'Boletim_'.$record->pessoa->nome.'.pdf';

        return $pdf->download($filename);
    }
}

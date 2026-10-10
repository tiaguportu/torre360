<?php

namespace App\Http\Controllers;

use App\Models\HistoricoEscolar;
use App\Services\HistoricoEscolarService;
use Illuminate\Http\Request;

class HistoricoEscolarPDFController extends Controller
{
    public function download(HistoricoEscolar $record, Request $request, HistoricoEscolarService $service)
    {
        $user = $request->user();

        // Histórico escolar tem dados pessoais e notas de todo o percurso: equipe administrativa ou permissão específica.
        if (! $user->isEquipeAdministrativa() && ! $user->can('View:HistoricoEscolar')) {
            $idsAcessiveis = $user->pessoasAcessiveis()->pluck('id');
            abort_unless($idsAcessiveis->contains($record->pessoa_id), 403, 'Acesso não autorizado a este documento.');
        }

        $pdf = $service->gerarPdf($record);
        $nomeAluno = str($record->pessoa?->nome ?? 'Estudante')->slug();
        $filename = 'Historico_Escolar_'.$nomeAluno.'.pdf';

        return $pdf->download($filename);
    }

    public function stream(HistoricoEscolar $record, Request $request, HistoricoEscolarService $service)
    {
        $user = $request->user();

        // Histórico escolar tem dados pessoais e notas de todo o percurso: equipe administrativa ou permissão específica.
        if (! $user->isEquipeAdministrativa() && ! $user->can('View:HistoricoEscolar')) {
            $idsAcessiveis = $user->pessoasAcessiveis()->pluck('id');
            abort_unless($idsAcessiveis->contains($record->pessoa_id), 403, 'Acesso não autorizado a este documento.');
        }

        $pdf = $service->gerarPdf($record);
        $nomeAluno = str($record->pessoa?->nome ?? 'Estudante')->slug();
        $filename = 'Historico_Escolar_'.$nomeAluno.'.pdf';

        return $pdf->stream($filename);
    }
}

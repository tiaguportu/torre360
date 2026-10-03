<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Interessado;
use App\Services\CrmIaVendasService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DossieIaPdfController extends Controller
{
    /**
     * Faz o download direto do Dossiê Estratégico IA em PDF.
     */
    public function download(Interessado $record, Request $request, CrmIaVendasService $service)
    {
        $this->authorizeAccess($request, $record);

        $pdf = $service->gerarPdfDossie($record);
        $nomeLead = Str::slug($record->pessoa?->nome ?? 'Lead');
        $filename = "Dossie_Estrategico_{$nomeLead}_{$record->id}.pdf";

        return $pdf->download($filename);
    }

    /**
     * Exibe o Dossiê Estratégico IA em PDF diretamente no navegador (stream).
     */
    public function stream(Interessado $record, Request $request, CrmIaVendasService $service)
    {
        $this->authorizeAccess($request, $record);

        $pdf = $service->gerarPdfDossie($record);
        $nomeLead = Str::slug($record->pessoa?->nome ?? 'Lead');
        $filename = "Dossie_Estrategico_{$nomeLead}_{$record->id}.pdf";

        return $pdf->stream($filename);
    }

    private function authorizeAccess(Request $request, Interessado $record): void
    {
        $user = $request->user();

        abort_unless($user !== null, 401, 'Não autenticado.');

        if (! $user->isStaff() && ! $user->can('View:Interessado') && ! $user->can('ViewAny:Interessado')) {
            abort(403, 'Acesso não autorizado a este dossiê.');
        }
    }
}

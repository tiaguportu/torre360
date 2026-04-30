<?php

namespace App\Http\Controllers\Documentos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class VisualizarDocumentoController extends Controller
{
    /**
     * Serve um arquivo protegido do disco local.
     */
    public function __invoke(Request $request, string $path): Response
    {
        if (! auth()->check()) {
            return redirect()->route('filament.admin.auth.login');
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            Log::warning("Arquivo não encontrado no disco local: {$path}");
            abort(404, 'Arquivo não encontrado.');
        }

        $fullPath = $disk->path($path);
        Log::info("Servindo arquivo do caminho: {$fullPath}");

        return response()->file($fullPath, [
            'Content-Disposition' => 'inline',
        ]);
    }
}

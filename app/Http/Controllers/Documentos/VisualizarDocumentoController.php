<?php

namespace App\Http\Controllers\Documentos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VisualizarDocumentoController extends Controller
{
    /**
     * Serve um arquivo protegido do disco local.
     */
    public function __invoke(Request $request, string $path): BinaryFileResponse
    {
        // O middleware 'auth' na rota já garante que o usuário está logado.
        
        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            abort(404, 'Arquivo não encontrado.');
        }

        $fullPath = $disk->path($path);

        return response()->file($fullPath, [
            'Content-Disposition' => 'inline',
        ]);
    }
}

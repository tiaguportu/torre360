<?php

namespace App\Http\Controllers;

use App\Models\Livro;
use App\Services\BarcodeService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BibliotecaEtiquetasController extends Controller
{
    public function imprimir(Request $request, BarcodeService $barcodeService): View
    {
        $livroIds = $request->input('livros');
        if (! is_array($livroIds)) {
            $livroIds = array_filter(explode(',', (string) $livroIds));
        }

        $copiasPorLivro = (int) $request->input('copias', 1);
        $copiasPorLivro = max(1, min(100, $copiasPorLivro));

        $livros = Livro::whereIn('id', $livroIds)->get();

        $itens = [];
        foreach ($livros as $livro) {
            $codigo = $livro->codigo ?: ('LIV-'.str_pad((string) $livro->id, 5, '0', STR_PAD_LEFT));
            $barcodeSvg = $barcodeService->gerarSvg($codigo, 45, 1.8);

            $itens[] = [
                'livro' => $livro,
                'barcodeSvg' => $barcodeSvg,
                'copias' => $copiasPorLivro,
            ];
        }

        return view('filament.pages.biblioteca.etiquetas-impressao', [
            'livros' => $itens,
        ]);
    }
}

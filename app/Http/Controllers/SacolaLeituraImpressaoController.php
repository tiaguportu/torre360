<?php

namespace App\Http\Controllers;

use App\Models\SacolaLeitura;
use App\Services\BarcodeService;
use Illuminate\View\View;

class SacolaLeituraImpressaoController extends Controller
{
    public function ficha(SacolaLeitura $sacola, BarcodeService $barcodeService): View
    {
        $sacola->load(['turma', 'responsavel', 'user', 'itens.livro']);

        $codigo = $sacola->codigo ?: ('SAC-'.str_pad((string) $sacola->id, 4, '0', STR_PAD_LEFT));
        $barcodeSvg = $barcodeService->gerarSvg($codigo, 40, 1.6);

        return view('filament.pages.biblioteca.sacola-ficha-impressao', [
            'sacola' => $sacola,
            'barcodeSvg' => $barcodeSvg,
        ]);
    }
}

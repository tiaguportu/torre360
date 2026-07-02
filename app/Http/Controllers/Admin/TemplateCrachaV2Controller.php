<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TemplateCrachaEntidade;
use App\Http\Controllers\Controller;
use App\Models\TemplateCrachaV2;
use App\Services\TemplateCrachaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TemplateCrachaV2Controller extends Controller
{
    /**
     * Exibe o editor de canvas do crachá V2 (SVG-Edit).
     */
    public function editor(TemplateCrachaV2 $templateCrachaV2): View
    {
        $todasVariaveis = [
            'pessoa' => TemplateCrachaService::getVariaveisPorEntidade(TemplateCrachaEntidade::PESSOA),
            'turma' => TemplateCrachaService::getVariaveisPorEntidade(TemplateCrachaEntidade::TURMA),
        ];

        return view('admin.cracha-v2-editor', compact('templateCrachaV2', 'todasVariaveis'));
    }

    /**
     * Salva o conteúdo do SVG no banco de dados.
     */
    public function save(Request $request, TemplateCrachaV2 $templateCrachaV2): JsonResponse
    {
        $request->validate([
            'svg_content' => 'required|string',
        ]);

        $templateCrachaV2->update([
            'svg_content' => $request->input('svg_content'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Template de crachá salvo com sucesso!',
        ]);
    }
}

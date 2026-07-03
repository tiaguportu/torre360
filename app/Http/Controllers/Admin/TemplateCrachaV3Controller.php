<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TemplateCrachaEntidade;
use App\Http\Controllers\Controller;
use App\Models\TemplateCrachaV3;
use App\Services\TemplateCrachaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TemplateCrachaV3Controller extends Controller
{
    /**
     * Exibe o editor de canvas do crachá V3 (Moveable).
     */
    public function editor(TemplateCrachaV3 $templateCrachaV3): View
    {
        $todasVariaveis = [
            'pessoa' => TemplateCrachaService::getVariaveisPorEntidade(TemplateCrachaEntidade::PESSOA),
            'turma' => TemplateCrachaService::getVariaveisPorEntidade(TemplateCrachaEntidade::TURMA),
        ];

        return view('admin.cracha-v3-editor', compact('templateCrachaV3', 'todasVariaveis'));
    }

    /**
     * Salva o JSON de layout no banco de dados.
     */
    public function save(Request $request, TemplateCrachaV3 $templateCrachaV3): JsonResponse
    {
        $request->validate([
            'dados_json' => 'required|array',
        ]);

        $templateCrachaV3->update([
            'dados_json' => $request->input('dados_json'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Template de crachá V3 salvo com sucesso!',
        ]);
    }
}

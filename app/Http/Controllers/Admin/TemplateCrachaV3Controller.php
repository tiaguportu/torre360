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
    public function editor(Request $request, TemplateCrachaV3 $templateCrachaV3): View
    {
        $user = $request->user();
        abort_unless(
            $user && ($user->can('view', $templateCrachaV3) || $user->can('update', $templateCrachaV3) || $user->can('View:TemplateCrachaV3') || $user->can('Update:TemplateCrachaV3')),
            403,
            'Você não possui permissão para visualizar o editor deste template de crachá.'
        );

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
        $user = $request->user();
        abort_unless(
            $user && ($user->can('update', $templateCrachaV3) || $user->can('Update:TemplateCrachaV3')),
            403,
            'Você não possui permissão para salvar alterações neste template de crachá.'
        );

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

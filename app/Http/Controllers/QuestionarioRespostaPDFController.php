<?php

namespace App\Http\Controllers;

use App\Filament\Resources\QuestionarioRespostas\QuestionarioRespostaResource;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class QuestionarioRespostaPDFController extends Controller
{
    public function download(Request $request)
    {
        $ids = $request->query('ids');

        if (empty($ids) || ! is_array($ids)) {
            abort(404, 'Nenhum questionário selecionado.');
        }

        $idsLimpos = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($idsLimpos)) {
            abort(404, 'Nenhum questionário selecionado.');
        }

        // Carrega os registros que o usuário tem permissão para visualizar
        $records = QuestionarioRespostaResource::getEloquentQuery()
            ->whereIn('id', $idsLimpos)
            ->with(['questionario', 'user', 'perguntaRespostas.pergunta.bloco'])
            ->get();

        if ($records->count() !== count($idsLimpos)) {
            abort(403, 'Acesso não autorizado a um ou mais questionários selecionados.');
        }

        $pdf = Pdf::loadView('pdfs.comparacao-questionarios', [
            'records' => $records,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('comparacao_respostas_questionarios.pdf');
    }
}

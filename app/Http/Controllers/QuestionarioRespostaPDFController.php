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

        // Carrega os registros que o usuário tem permissão para visualizar
        $records = QuestionarioRespostaResource::getEloquentQuery()
            ->whereIn('id', $ids)
            ->with(['questionario', 'user', 'perguntaRespostas.pergunta.bloco'])
            ->get();

        if ($records->isEmpty()) {
            abort(404, 'Nenhum questionário selecionado ou acesso negado.');
        }

        $pdf = Pdf::loadView('pdfs.comparacao-questionarios', [
            'records' => $records,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('comparacao_respostas_questionarios.pdf');
    }
}

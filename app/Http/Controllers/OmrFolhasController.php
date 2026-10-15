<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Avaliacao;
use App\Models\GabaritoFolha;
use App\Services\Omr\FolhaGabaritoPdfService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OmrFolhasController extends Controller
{
    public function __construct(
        protected FolhaGabaritoPdfService $pdfService,
    ) {}

    /**
     * Download de PDF de folhas gerado em fila assíncrona.
     */
    public function downloadArquivo(Request $request): StreamedResponse
    {
        $pathCodificado = (string) $request->query('path', '');
        $caminho = base64_decode($pathCodificado);

        if (empty($caminho) || str_contains($caminho, '..') || str_contains($caminho, "\0")) {
            abort(404, 'Arquivo de gabarito não encontrado ou inválido.');
        }

        // Validação estrita do padrão de caminho gerado pelo sistema
        if (! preg_match('/^gabaritos\/folhas_turma_avaliacao_(\d+)_[a-zA-Z0-9]+\.pdf$/', $caminho, $matches)) {
            abort(404, 'Arquivo de gabarito não encontrado ou caminho não autorizado.');
        }

        $avaliacaoId = (int) $matches[1];
        $avaliacao = Avaliacao::find($avaliacaoId);
        if (! $avaliacao) {
            abort(404, 'Avaliação não encontrada.');
        }

        // Proteção IDOR: autoriza a avaliação específica para o usuário autenticado
        Gate::authorize('gerenciarGabarito', $avaliacao);

        if (! Storage::disk('local')->exists($caminho)) {
            abort(404, 'Arquivo de gabarito não encontrado ou expirado.');
        }

        return Storage::disk('local')->download($caminho, basename($caminho));
    }

    /**
     * Gera e baixa em tempo de requisição a folha individual de um aluno.
     */
    public function downloadFolha(GabaritoFolha $folha): Response
    {
        Gate::authorize('gerenciarGabarito', $folha->avaliacao);

        $pdfContent = $this->pdfService->gerarPdfFolha($folha);
        $nomeArquivo = 'folha_gabarito_'.$folha->uuid.'.pdf';

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$nomeArquivo}\"",
        ]);
    }

    /**
     * Gera e baixa em tempo de requisição a folha avulsa / em branco.
     */
    public function downloadFolhaEmBranco(Avaliacao $avaliacao): Response
    {
        Gate::authorize('gerenciarGabarito', $avaliacao);

        $pdfContent = $this->pdfService->gerarPdfFolhaEmBranco($avaliacao);
        $nomeArquivo = 'folha_gabarito_em_branco_avaliacao_'.$avaliacao->id.'.pdf';

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$nomeArquivo}\"",
        ]);
    }

    /**
     * Gera e baixa em tempo de requisição as folhas da turma inteira.
     */
    public function downloadTurma(Avaliacao $avaliacao): Response
    {
        Gate::authorize('gerenciarGabarito', $avaliacao);

        $pdfContent = $this->pdfService->gerarPdfTurma($avaliacao);
        $nomeArquivo = 'folhas_turma_avaliacao_'.$avaliacao->id.'.pdf';

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$nomeArquivo}\"",
        ]);
    }
}

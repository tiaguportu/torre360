<?php

namespace App\Http\Controllers\Documentos;

use App\Http\Controllers\Controller;
use App\Models\AtendimentoMensagem;
use App\Models\DocumentoInserido;
use App\Models\SolicitacaoDocumento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class VisualizarDocumentoController extends Controller
{
    /**
     * Serve um arquivo protegido do disco local com autorização e sanitização contra path traversal.
     */
    public function __invoke(Request $request, string $path): Response
    {
        if (! auth()->check()) {
            return redirect()->route('filament.admin.auth.login');
        }

        $user = $request->user();

        // 1. Sanitização estrita contra Path Traversal e injeção de caminho
        if (str_contains($path, '..') || str_contains($path, '\\') || str_contains($path, "\0")) {
            abort(400, 'Caminho de arquivo inválido.');
        }

        // 2. Determinação e verificação de existência no disco
        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            // Suporte de transição: caso seja documento emitido em formato legado no disco public
            if (str_starts_with($path, 'documentos_emitidos/') && Storage::disk('public')->exists($path)) {
                $disk = Storage::disk('public');
            } else {
                abort(404, 'Arquivo não encontrado.');
            }
        }

        // 3. Validação de Autorização por Contexto
        $autorizado = false;

        // Contexto A: Documento oficial emitido
        if (str_starts_with($path, 'documentos_emitidos/')) {
            $solicitacao = SolicitacaoDocumento::where('arquivo_path', $path)->first();
            if ($solicitacao && $solicitacao->isAccessibleBy($user)) {
                $autorizado = true;
            } elseif (! $solicitacao && $user->isStaff()) {
                $autorizado = true;
            }
        }
        // Contexto B: Documento de aluno/candidato inserido (RG, CPF, Certidão)
        elseif (str_starts_with($path, 'documentos_alunos/') || str_starts_with($path, 'documentos_candidatos/') || str_starts_with($path, 'matriculas_online/')) {
            $documentoInserido = DocumentoInserido::where('arquivo_path', $path)->first();
            if ($documentoInserido && $documentoInserido->isAccessibleBy($user)) {
                $autorizado = true;
            } elseif (! $documentoInserido && $user->isStaff()) {
                $autorizado = true;
            }
        }
        // Contexto C: Anexos de chamados de atendimento
        elseif (str_starts_with($path, 'atendimentos/anexos/')) {
            if ($user->isStaff()) {
                $autorizado = true;
            } else {
                $mensagem = AtendimentoMensagem::where('anexo_path', $path)->with('chamado.matricula')->first();
                if ($mensagem && $mensagem->chamado) {
                    $idsAcessiveis = $user->pessoasAcessiveis()->pluck('id');
                    $chamado = $mensagem->chamado;
                    if ($idsAcessiveis->contains($chamado->solicitante_id) || ($chamado->matricula && $chamado->matricula->isAccessibleBy($user))) {
                        $autorizado = true;
                    }
                }
            }
        }
        // Contexto D: Arquivos gerais de staff
        elseif ($user->isStaff()) {
            $autorizado = true;
        }

        if (! $autorizado) {
            abort(403, 'Acesso não autorizado a este documento.');
        }

        $fullPath = $disk->path($path);
        $filename = basename($path);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $mimeType = null;
        try {
            $mimeType = $disk->mimeType($path) ?: (function_exists('mime_content_type') ? @mime_content_type($fullPath) : null);
        } catch (\Throwable) {
            $mimeType = null;
        }
        $mimeType = strtolower((string) ($mimeType ?? 'application/octet-stream'));

        // SVGs e arquivos HTML/XML enviados não devem ser renderizados inline para evitar Stored XSS
        $isPotentiallyDangerousInline = in_array($extension, ['svg', 'html', 'htm', 'xhtml', 'xml'], true)
            || str_contains($mimeType, 'svg')
            || str_contains($mimeType, 'html')
            || str_contains($mimeType, 'xml');

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ];

        if ($isPotentiallyDangerousInline) {
            return response()->download($fullPath, $filename, $headers);
        }

        return response()->file($fullPath, array_merge($headers, [
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]));
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Relatorios;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Entrega segura de relatórios volumosos gerados assincronamente pela fila (Queue).
 *
 * Salvaguardas de Segurança:
 * 1. Rota protegida obrigatoriamente por Assinatura Digital Criptográfica (middleware 'signed').
 * 2. Autenticação obrigatória (usuário ativo).
 * 3. Sanitização contra Path Traversal e injeção de caracteres no token.
 * 4. Isolamento contra execução de scripts: CSP sandbox e MIME application/pdf forçado.
 */
class DownloadRelatorioTemporarioController extends Controller
{
    public function __invoke(Request $request, string $token): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            abort(403, 'Acesso não autorizado: conta de usuário inativa ou inexistente.');
        }

        // Sanitização estrita contra Path Traversal: token deve conter apenas caracteres alfanuméricos, hífens e underlines
        if (! preg_match('/^[a-zA-Z0-9_\-]+$/', $token)) {
            abort(400, 'Identificador de relatório inválido.');
        }

        $caminhoRelativo = "relatorios_gerados/{$token}.pdf";
        $disco = Storage::disk('local');

        if (! $disco->exists($caminhoRelativo)) {
            abort(404, 'O relatório solicitado não foi encontrado ou seu período de retenção expirou.');
        }

        $caminhoAbsoluto = $disco->path($caminhoRelativo);
        $nomeArquivo = (string) $request->query('filename', "relatorio-{$token}.pdf");

        // Limpa o nome do arquivo para o cabeçalho Content-Disposition
        $nomeArquivoSanitizado = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $nomeArquivo) ?: 'relatorio.pdf';

        if (! str_ends_with($nomeArquivoSanitizado, '.pdf')) {
            $nomeArquivoSanitizado .= '.pdf';
        }

        $headers = [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ];

        return response()->download($caminhoAbsoluto, $nomeArquivoSanitizado, $headers)->setPrivate();
    }
}

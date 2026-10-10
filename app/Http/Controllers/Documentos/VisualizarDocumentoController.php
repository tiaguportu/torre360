<?php

namespace App\Http\Controllers\Documentos;

use App\Http\Controllers\Controller;
use App\Models\AtendimentoMensagem;
use App\Models\DocumentoInserido;
use App\Models\SolicitacaoDocumento;
use App\Support\TiposArquivo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class VisualizarDocumentoController extends Controller
{
    /**
     * Pastas de arquivos que o professor pode abrir por ser da equipe: fotos de alunos (listas de turma), materiais
     * e planos de aula e registros do dia a dia da turma. Todo o resto (documentos pessoais, contratos, extratos,
     * exportações, áudios e prints do CRM...) é só da equipe administrativa ou de quem tem a permissão específica.
     */
    private const PASTAS_LIBERADAS_AO_PROFESSOR = [
        'pessoas_fotos/', 'materiais-aula/', 'planos-aula/', 'rotina-diaria/', 'anotacoes-os/', 'ordem-servicos/',
    ];

    /**
     * Serve um arquivo protegido do disco local com autorização e sanitização contra path traversal.
     */
    public function __invoke(Request $request, string $path): Response
    {
        if (! auth()->check()) {
            return redirect()->route('filament.admin.auth.login');
        }

        $user = $request->user();

        if (! $user || ! $user->is_active) {
            auth()->logout();
            abort(403, 'Acesso não autorizado: conta de usuário inativa ou desativada.');
        }

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
            } elseif (! $solicitacao && $user->isEquipeAdministrativa()) {
                $autorizado = true;
            }
        }
        // Contexto B: Documento de aluno/candidato inserido (RG, CPF, Certidão)
        elseif (str_starts_with($path, 'documentos_alunos/') || str_starts_with($path, 'documentos_candidatos/') || str_starts_with($path, 'matriculas_online/')) {
            $documentoInserido = DocumentoInserido::where('arquivo_path', $path)->first();
            if ($documentoInserido && $documentoInserido->isAccessibleBy($user)) {
                $autorizado = true;
            } elseif (! $documentoInserido && $user->isEquipeAdministrativa()) {
                $autorizado = true;
            }
        }
        // Contexto C: Anexos de chamados de atendimento
        elseif (str_starts_with($path, 'atendimentos/anexos/')) {
            if ($user->isEquipeAdministrativa() || $user->can('View:AtendimentoChamado')) {
                $autorizado = true;
            } else {
                $mensagem = AtendimentoMensagem::where('anexo_path', $path)->with('chamado.matricula')->first();
                if ($mensagem && $mensagem->chamado) {
                    $idsAcessiveis = $user->pessoasAcessiveis()->pluck('id');
                    $chamado = $mensagem->chamado;
                    if ($idsAcessiveis->contains($chamado->solicitante_id) || ($chamado->matricula && $chamado->matricula->isAccessibleByFamilia($user))) {
                        $autorizado = true;
                    }
                }
            }
        }
        // Contexto D: Demais arquivos. A equipe administrativa abre qualquer um; o professor, só as pastas liberadas.
        elseif ($user->isEquipeAdministrativa()) {
            $autorizado = true;
        } elseif ($user->isStaff() && $this->pastaLiberadaAoProfessor($path)) {
            $autorizado = true;
        }

        if (! $autorizado) {
            abort(403, 'Acesso não autorizado a este documento.');
        }

        return $this->servir($disk->path($path));
    }

    private function pastaLiberadaAoProfessor(string $path): bool
    {
        foreach (self::PASTAS_LIBERADAS_AO_PROFESSOR as $pasta) {
            if (str_starts_with($path, $pasta)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Os arquivos são enviados por usuários e servidos na mesma origem do painel. O tipo é detectado pelo CONTEÚDO
     * (nunca pela extensão): só PDF e imagens raster abrem na página; qualquer outra coisa (HTML, SVG, XML, scripts...)
     * baixa como anexo e, mesmo se alguém a abrir, roda isolada (CSP `sandbox`), sem acesso à sessão do painel.
     */
    private function servir(string $caminho): Response
    {
        $tipo = (new \finfo(FILEINFO_MIME_TYPE))->file($caminho) ?: 'application/octet-stream';
        $exibivel = in_array($tipo, TiposArquivo::exibiveisNoNavegador(), true);

        $cabecalhos = [
            'Content-Type' => $tipo,
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ];

        if ($exibivel) {
            // Só `frame-ancestors`: a CSP global traz `object-src 'none'`, que o visualizador de PDF de alguns navegadores
            // não tolera numa resposta PDF. Este arquivo é PDF/imagem raster, que não executa script na origem do sistema.
            return response()->file($caminho, $cabecalhos + ['Content-Security-Policy' => "frame-ancestors 'self'"])
                ->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($caminho))
                ->setPrivate();
        }

        // Tipo genérico de propósito: o navegador baixa o arquivo em vez de interpretá-lo.
        return response()->download($caminho, basename($caminho), [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ])->setPrivate();
    }
}

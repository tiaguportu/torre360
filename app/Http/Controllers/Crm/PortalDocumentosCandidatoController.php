<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Enums\SituacaoDocumento;
use App\Http\Controllers\Controller;
use App\Models\DocumentoInserido;
use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\TipoContatoInteressado;
use App\Models\TipoDocumento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PortalDocumentosCandidatoController extends Controller
{
    /**
     * Exibe o portal público de envio e acompanhamento de documentos do candidato.
     */
    public function show(string $token): View
    {
        $interessado = Interessado::where('token_documentos', $token)->firstOrFail();

        $interessado->loadMissing([
            'pessoa',
            'dependentes.serie.curso',
            'documentosInseridos.tipoDocumento',
        ]);

        $tiposRequeridos = $interessado->documentosRequeridos();
        $documentosInseridos = $interessado->documentosInseridos;
        $progresso = $interessado->progressoDocumentos();

        return view('candidato.portal-documentos', [
            'interessado' => $interessado,
            'tiposRequeridos' => $tiposRequeridos,
            'documentosInseridos' => $documentosInseridos,
            'progresso' => $progresso,
            'token' => $token,
        ]);
    }

    /**
     * Recebe o upload de um documento enviado pela família.
     */
    public function upload(Request $request, string $token): RedirectResponse
    {
        $interessado = Interessado::where('token_documentos', $token)->firstOrFail();

        $validated = $request->validate([
            'tipo_documento_id' => ['required', 'exists:tipo_documento,id'],
            'interessado_dependente_id' => ['nullable', 'exists:interessado_dependente,id'],
            'arquivo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'], // Max 10MB
        ], [
            'arquivo.required' => 'Por favor, selecione ou tire uma foto do documento.',
            'arquivo.mimes' => 'O documento deve ser um arquivo PDF ou uma imagem (JPG, PNG, WebP).',
            'arquivo.max' => 'O tamanho máximo permitido para o arquivo é de 10 MB.',
        ]);

        $tipoDoc = TipoDocumento::findOrFail($validated['tipo_documento_id']);
        $file = $request->file('arquivo');

        // Salva de forma segura no disco privado
        $path = $file->store('documentos_candidatos/'.$interessado->id, 'local');
        $hash = hash_file('sha256', $file->getRealPath());

        // Se já existia um documento para este tipo e dependente, atualiza; senão, cria novo
        $doc = DocumentoInserido::updateOrCreate(
            [
                'interessado_id' => $interessado->id,
                'tipo_documento_id' => $tipoDoc->id,
                'interessado_dependente_id' => $validated['interessado_dependente_id'] ?? null,
            ],
            [
                'arquivo_path' => $path,
                'nome_arquivo_original' => $file->getClientOriginalName(),
                'hash_arquivo' => $hash,
                'status' => SituacaoDocumento::EM_ANALISE,
                'observacoes' => null, // Limpa qualquer rejeição anterior
            ]
        );

        // Registra histórico na timeline do lead
        $tipoContato = TipoContatoInteressado::firstOrCreate(['nome' => 'Portal de Admissão']);
        HistoricoContato::create([
            'interessado_id' => $interessado->id,
            'usuario_id' => $interessado->usuario_id,
            'tipo_contato_interessado_id' => $tipoContato->id,
            'relato' => "📎 A família anexou o documento '{$tipoDoc->nome}' pelo Portal de Pré-Admissão.",
            'data_contato' => now(),
        ]);

        return back()->with('sucesso', "Documento '{$tipoDoc->nome}' enviado com sucesso! Nossa secretaria irá analisá-lo.");
    }

    /**
     * Permite à família remover um documento em análise ou rejeitado.
     */
    public function remover(string $token, int $documentoId): RedirectResponse
    {
        $interessado = Interessado::where('token_documentos', $token)->firstOrFail();

        $documento = DocumentoInserido::where('interessado_id', $interessado->id)
            ->where('id', $documentoId)
            ->firstOrFail();

        if ($documento->status === SituacaoDocumento::VERIFICADO) {
            return back()->with('erro', 'Documentos já verificados pela secretaria não podem ser excluídos diretamente.');
        }

        if ($documento->arquivo_path && Storage::disk('local')->exists($documento->arquivo_path)) {
            Storage::disk('local')->delete($documento->arquivo_path);
        }

        $documento->delete();

        return back()->with('sucesso', 'Documento removido.');
    }
}

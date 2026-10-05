<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Enums\SituacaoDocumento;
use App\Http\Controllers\Controller;
use App\Jobs\ValidarDocumentoComIaJob;
use App\Models\DocumentoInserido;
use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\TipoContatoInteressado;
use App\Models\TipoDocumento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PortalDocumentosCandidatoController extends Controller
{
    /**
     * Máximo de envios por candidato por hora: cada arquivo dispara uma análise paga por IA,
     * então um link vazado não pode ser usado para esgotar a cota/custo do Gemini.
     */
    private const MAX_ENVIOS_POR_HORA = 30;

    /**
     * Exibe o portal público de envio e acompanhamento de documentos do candidato.
     */
    public function show(string $token): View|Response
    {
        $interessado = $this->localizarInteressado($token);

        if (! $interessado) {
            return $this->linkInvalido();
        }

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
    public function upload(Request $request, string $token): RedirectResponse|Response
    {
        $interessado = $this->localizarInteressado($token);

        if (! $interessado) {
            return $this->linkInvalido();
        }

        $validated = $request->validate([
            'tipo_documento_id' => ['required', 'exists:tipo_documento,id'],
            // O dependente precisa pertencer a este candidato: um id de outra família não pode ser aceito.
            'interessado_dependente_id' => ['nullable', Rule::exists('interessado_dependente', 'id')->where('interessado_id', $interessado->id)],
            'arquivo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'], // Max 10MB
        ], [
            'arquivo.required' => 'Por favor, selecione ou tire uma foto do documento.',
            'arquivo.mimes' => 'O documento deve ser um arquivo PDF ou uma imagem (JPG, PNG, WebP).',
            'arquivo.max' => 'O tamanho máximo permitido para o arquivo é de 10 MB.',
            'interessado_dependente_id.exists' => 'O aluno selecionado não pertence a esta inscrição.',
        ]);

        $limiteChave = 'portal-documentos-upload:'.$interessado->id;

        if (RateLimiter::tooManyAttempts($limiteChave, self::MAX_ENVIOS_POR_HORA)) {
            return back()->with('erro', 'Você atingiu o limite de envios por hora. Aguarde um pouco e tente novamente ou fale com a secretaria.');
        }

        $tipoDoc = TipoDocumento::findOrFail($validated['tipo_documento_id']);
        $dependenteId = $validated['interessado_dependente_id'] ?? null;

        // Só considera documentos ainda da pré-admissão: os já migrados para uma matrícula pertencem a ela.
        $existente = DocumentoInserido::query()
            ->where('interessado_id', $interessado->id)
            ->where('tipo_documento_id', $tipoDoc->id)
            ->where('interessado_dependente_id', $dependenteId)
            ->whereNull('matricula_id')
            ->first();

        // Documento já conferido pela secretaria não pode ser trocado pela família (mesma regra de `remover`).
        if ($existente?->status === SituacaoDocumento::VERIFICADO) {
            return back()->with('erro', "O documento '{$tipoDoc->nome}' já foi verificado pela secretaria e não pode ser substituído. Em caso de dúvida, fale com a secretaria.");
        }

        RateLimiter::hit($limiteChave, 3600);

        // Salva de forma segura no disco privado
        $file = $request->file('arquivo');
        $path = $file->store('documentos_candidatos/'.$interessado->id, 'local');
        $hash = hash_file('sha256', $file->getRealPath());

        $atributos = [
            'arquivo_path' => $path,
            'nome_arquivo_original' => $file->getClientOriginalName(),
            'hash_arquivo' => $hash,
            'status' => SituacaoDocumento::EM_ANALISE,
            'observacoes' => null, // Limpa qualquer rejeição anterior
            // O parecer da IA pertencia ao arquivo anterior; o novo será analisado em seguida.
            'dados_ia' => null,
            'analisado_ia_em' => null,
        ];

        if ($existente) {
            $arquivoAnterior = $existente->arquivo_path;
            $existente->update($atributos);
            $doc = $existente;

            // Não deixa o arquivo substituído (com dados pessoais) órfão no disco.
            if ($arquivoAnterior && $arquivoAnterior !== $path && Storage::disk('local')->exists($arquivoAnterior)) {
                Storage::disk('local')->delete($arquivoAnterior);
            }
        } else {
            $doc = DocumentoInserido::create($atributos + [
                'interessado_id' => $interessado->id,
                'tipo_documento_id' => $tipoDoc->id,
                'interessado_dependente_id' => $dependenteId,
            ]);
        }

        // Dispara a validação inteligente em segundo plano sem travar a navegação da família
        ValidarDocumentoComIaJob::dispatch($doc->id);

        // Registra histórico na timeline do lead
        $tipoContato = TipoContatoInteressado::firstOrCreate(['nome' => 'Portal de Admissão']);
        HistoricoContato::create([
            'interessado_id' => $interessado->id,
            'usuario_id' => $interessado->usuario_id,
            'tipo_contato_interessado_id' => $tipoContato->id,
            'relato' => "📎 A família anexou o documento '{$tipoDoc->nome}' pelo Portal de Pré-Admissão.",
            'data_contato' => now(),
        ]);

        return back()->with('sucesso', "Documento '{$tipoDoc->nome}' enviado com sucesso! Nossa equipe e IA estão processando a validação em segundo plano.");
    }

    /**
     * Permite à família remover um documento em análise ou rejeitado.
     */
    public function remover(string $token, int $documentoId): RedirectResponse|Response
    {
        $interessado = $this->localizarInteressado($token);

        if (! $interessado) {
            return $this->linkInvalido();
        }

        $documento = DocumentoInserido::where('interessado_id', $interessado->id)
            ->whereNull('matricula_id')
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

    /**
     * Resolve o candidato pelo token do link, ignorando links expirados.
     */
    private function localizarInteressado(string $token): ?Interessado
    {
        return Interessado::comTokenDocumentosValido($token)->first();
    }

    /**
     * Resposta única para link inexistente ou expirado: não revela se o token já existiu.
     */
    private function linkInvalido(): Response
    {
        return response()->view('candidato.link-expirado', [], 410);
    }
}

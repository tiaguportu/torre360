<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Enums\Sexo;
use App\Enums\SituacaoDocumento;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\SalvarDadosPreAdmissaoRequest;
use App\Jobs\ValidarDocumentoComIaJob;
use App\Models\DocumentoInserido;
use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\Serie;
use App\Models\TipoContatoInteressado;
use App\Models\TipoDocumento;
use App\Models\TipoVinculo;
use App\Services\ConviteMatriculaService;
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
     * Máximo de envios por candidato por hora: cada arquivo dispara uma análise por IA,
     * então um link vazado não pode ser usado para esgotar a cota/custo do Gemini.
     */
    private const MAX_ENVIOS_POR_HORA = 30;

    /**
     * Redirecionamento permanente/suave de links legados de convite (/quero-matricular/convite/{token})
     * para a experiência unificada do portal de admissão (/admissao/{token}).
     */
    public function redirecionarLegadoConvite(string $token): RedirectResponse|Response
    {
        $interessado = $this->localizarInteressado($token);

        if (! $interessado) {
            return $this->linkInvalido();
        }

        $tokenAlvo = $interessado->obterOuCriarTokenDocumentos();

        return redirect()->route('candidato.documentos.show', ['token' => $tokenAlvo]);
    }

    /**
     * Exibe o portal público unificado de pré-admissão, confirmação de dados e checklist de documentos.
     */
    public function show(Request $request, string $token): View|Response
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
        $docsContrato = $interessado->documentosContrato();
        $docsHistorico = $interessado->documentosHistorico();
        $docsOpcionais = $interessado->documentosOpcionais();

        $documentosInseridos = $interessado->documentosInseridos;
        $progresso = $interessado->progressoDocumentos();
        $todosDocsContratoEntregues = $interessado->todosDocsContratoEntregues();

        // Se a família já preencheu a pré-matrícula anteriormente, a aba padrão é documentos
        $dadosPreenchidos = filled($interessado->dados_pre_matricula);
        $abaPadrao = $dadosPreenchidos ? 'documentos' : 'dados';
        $abaAtiva = $request->get('aba', $abaPadrao);
        $statusAbas = $interessado->resumoPendenciasPortal();

        return view('candidato.portal-documentos', [
            'interessado' => $interessado,
            'tiposRequeridos' => $tiposRequeridos,
            'docsContrato' => $docsContrato,
            'docsHistorico' => $docsHistorico,
            'docsOpcionais' => $docsOpcionais,
            'documentosInseridos' => $documentosInseridos,
            'progresso' => $progresso,
            'todosDocsContratoEntregues' => $todosDocsContratoEntregues,
            'token' => $token,
            'abaAtiva' => $abaAtiva,
            'statusAbas' => $statusAbas,
            'series' => Serie::with('curso')->orderBy('nome')->get(),
            'tiposVinculo' => TipoVinculo::orderBy('nome')->pluck('nome', 'id'),
            'sexos' => Sexo::cases(),
            'dadosPreMatricula' => $interessado->dados_pre_matricula ?? [],
        ]);
    }

    /**
     * Salva ou atualiza os dados cadastrais da família (responsáveis, endereço, dependentes)
     * e avança para o checklist de documentos.
     */
    public function salvarDadosCadastrais(
        SalvarDadosPreAdmissaoRequest $request,
        string $token,
        ConviteMatriculaService $conviteService
    ): RedirectResponse {
        $interessado = $request->getInteressado();

        if (! $interessado) {
            return redirect()->route('candidato.documentos.show', ['token' => $token]);
        }

        $validated = $request->validated();
        $responsavel = $validated['responsavel'];

        $conviteService->confirmar(
            $interessado,
            ['telefone' => $responsavel['telefone'], 'email' => $responsavel['email'] ?? null],
            $validated['dependentes'],
            $conviteService->montarPreMatricula($validated, $request->ip())
        );

        return redirect()
            ->route('candidato.documentos.show', ['token' => $token, 'aba' => 'documentos'])
            ->with('sucesso', 'Dados cadastrais confirmados com sucesso! Agora anexe os documentos solicitados abaixo.');
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

        if ($tipoDoc->isInterno()) {
            return back()->with('erro', 'Este documento é de uso interno da secretaria e não pode ser enviado pelo portal.');
        }

        $cursosDoLead = $interessado->cursosPretendidosIds();
        $temVinculoCursos = $tipoDoc->cursos()->exists();
        if ($temVinculoCursos && ! empty($cursosDoLead) && ! $tipoDoc->cursos()->whereIn('curso.id', $cursosDoLead)->exists()) {
            return back()->with('erro', "O documento '{$tipoDoc->nome}' não é aplicável aos cursos selecionados para esta inscrição.");
        }

        $dependenteId = $validated['interessado_dependente_id'] ?? null;

        // Só considera documentos ainda da pré-admissão: os já migrados para uma matrícula pertencem a ela.
        $existente = DocumentoInserido::query()
            ->where('interessado_id', $interessado->id)
            ->where('tipo_documento_id', $tipoDoc->id)
            ->where('interessado_dependente_id', $dependenteId)
            ->whereNull('matricula_id')
            ->first();

        // Documento já conferido pela secretaria não pode ser trocado pela família
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
            'observacoes' => null,
            'dados_ia' => null,
            'analisado_ia_em' => null,
        ];

        if ($existente) {
            $arquivoAnterior = $existente->arquivo_path;
            $existente->update($atributos);
            $doc = $existente;

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

        // Dispara validação inteligente em segundo plano
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

        return redirect()
            ->route('candidato.documentos.show', ['token' => $token, 'aba' => 'documentos'])
            ->with('sucesso', "Documento '{$tipoDoc->nome}' enviado com sucesso! Nossa equipe e IA estão processando a validação em segundo plano.");
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

        return redirect()
            ->route('candidato.documentos.show', ['token' => $token, 'aba' => 'documentos'])
            ->with('sucesso', 'Documento removido.');
    }

    /**
     * Resolve o candidato pelo token do link, ignorando links expirados.
     */
    private function localizarInteressado(string $token): ?Interessado
    {
        return Interessado::comTokenDocumentosValido($token)->first()
            ?? Interessado::where('token_convite', $token)->first();
    }

    /**
     * Resposta única para link inexistente ou expirado: não revela se o token já existiu.
     */
    private function linkInvalido(): Response
    {
        return response()->view('candidato.link-expirado', [], 410);
    }
}

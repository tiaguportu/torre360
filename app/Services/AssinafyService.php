<?php

namespace App\Services;

use App\Models\Contrato;
use App\Models\TemplateContrato;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Notifications\Notification;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AssinafyService
{
    protected string $apiUrl;

    protected ?string $apiKey = null;

    protected ?string $accountId = null;

    /**
     * Status do contrato correspondente a cada evento do webhook do Assinafy; eventos fora desta lista são
     * apenas informativos e não alteram o status. Catálogo oficial: docs/API_REFERENCE.md do SDK do Assinafy.
     *
     * @var array<string, string>
     */
    private const EVENTO_PARA_STATUS = [
        'document_uploaded' => 'enviado',
        'signature_requested' => 'enviado',
        'signer_signed_document' => 'enviado', // assinatura individual: o documento segue pendente até document_ready
        'document_ready' => 'ready', // todos os signatários assinaram
        'signer_rejected_document' => 'rejected',
        'user_rejected_document' => 'canceled',
        'document_processing_failed' => 'erro_envio',
        // Nomes legados (não constam no catálogo atual do Assinafy), mantidos por compatibilidade
        'signer_signed' => 'enviado',
        'signature_completed' => 'enviado',
        'document_signed' => 'signed',
        'document_completed' => 'signed',
        'document_refused' => 'rejected',
    ];

    /**
     * Status do documento na API do Assinafy → status do contrato (ausente = não altera o status).
     *
     * @var array<string, string>
     */
    private const STATUS_API_PARA_STATUS = [
        'pending_signature' => 'enviado',
        'ready' => 'ready',
        'certificating' => 'certificating',
        'certificated' => 'certificated',
        'rejected_by_signer' => 'rejected',
        'rejected_by_user' => 'canceled',
        'expired' => 'expired',
        'failed' => 'erro_envio',
    ];

    /**
     * Ordem das etapas até a conclusão; um contrato já assinado nunca volta para uma etapa anterior.
     *
     * @var array<string, int>
     */
    private const ORDEM_ETAPAS = [
        'pendente' => 0,
        'pending' => 0,
        'enviado' => 1,
        'ready' => 2,
        'certificating' => 3,
        'certificated' => 4,
        'signed' => 4,
        'completed' => 4,
    ];

    public function __construct()
    {
        $rawUrl = config('services.assinafy.url') ?? env('ASSINAFY_API_URL') ?? 'https://sandbox.assinafy.com.br/v1';
        $this->apiUrl = rtrim((string) $rawUrl, '/');
        $this->apiKey = config('services.assinafy.key') ?? env('ASSINAFY_API_KEY');
        $this->accountId = config('services.assinafy.account_id') ?? env('ASSINAFY_ACCOUNT_ID');

        // Ajuste Crítico: Para chamadas de API no Sandbox, a URL deve ser sandbox.assinafy.com.br
        // O endereço .pages.dev é apenas o frontend e retorna 405 para POSTs.
        if (str_contains($this->apiUrl, 'assinafy-app.pages.dev')) {
            $this->apiUrl = 'https://sandbox.assinafy.com.br/v1';
        }

        // Garante que a URL tenha o sufixo /v1 se necessário (caso venha da config sem ele)
        if (! str_contains($this->apiUrl, '/v1')) {
            $this->apiUrl .= '/v1';
        }
    }

    /**
     * Envia um contrato para assinatura na Assinafy seguindo o fluxo de 3 passos da documentação v1.
     * Suporta múltiplos signatários (todos os responsáveis financeiros com usuário vinculado).
     */
    public function enviarContrato(Contrato $contrato): array
    {
        try {
            if (empty($this->apiKey) || empty($this->accountId)) {
                return [
                    'success' => false,
                    'message' => 'Configuração do Assinafy pendente no servidor: As variáveis ASSINAFY_API_KEY e ASSINAFY_ACCOUNT_ID precisam ser definidas no arquivo .env.',
                ];
            }
            // 0. Carregar dados relacionados
            $contrato->load([
                'matricula.pessoa.responsaveis.users',
                'matricula.turma.serie.curso.unidade.representantesLegais.users',
                'matricula.periodoLetivo',
                'responsaveisFinanceiros.pessoa.users',
                'templateContrato',
            ]);

            $matricula = $contrato->matricula;

            if (! $matricula) {
                return ['success' => false, 'message' => "Contrato #{$contrato->id} não possui matrícula vinculada."];
            }

            $signatarios = $contrato->getSignatarios();

            // Determina qual signatário é o "alvo" para o redirecionamento (preferencialmente o usuário logado atual)
            $emailUsuarioLogado = auth()->user()?->email;
            $signatarioAlvo = null;

            if ($emailUsuarioLogado) {
                $emailUsuarioLogadoClean = strtolower(trim($emailUsuarioLogado));
                $signatarioAlvo = $signatarios->first(fn ($s) => $s['email'] === $emailUsuarioLogadoClean);
            }

            // Se o usuário logado não for um dos signatários, usa o primeiro como fallback
            $signatarioAlvo = $signatarioAlvo ?? $signatarios->first();

            $emailSignatario = $signatarioAlvo['email'] ?? '';
            $nomeSignatario = $signatarioAlvo['nome'] ?? '';

            $nomeAluno = $contrato->matricula?->pessoa?->nome ?? 'Aluno';
            $nomeArquivoBase = "Contrato - Escola Torre de Marfim - {$nomeAluno} - {$contrato->id}.pdf";

            // --- ETAPA A: Verificar se o documento já existe no Assinafy (Consulta API Multi-ambiente) ---
            Notification::make()->title('Consultando Assinafy para evitar duplicidade de documento...')->info()->send();

            $documentId = $contrato->assinafy_id;
            $urlsToTry = $this->getApiUrlsToTry($contrato);

            // Busca por nome do arquivo via API nos ambientes disponíveis
            if (! $documentId) {
                foreach ($urlsToTry as $url) {
                    $responseSearchDoc = Http::withHeaders([
                        'X-Api-Key' => $this->apiKey,
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                    ])->get("{$url}/accounts/{$this->accountId}/documents", [
                        'search' => $nomeArquivoBase,
                    ]);

                    if ($responseSearchDoc->successful()) {
                        $documents = $responseSearchDoc->json('data') ?? [];
                        foreach ($documents as $doc) {
                            if (($doc['name'] ?? '') === $nomeArquivoBase || ($doc['original_name'] ?? '') === $nomeArquivoBase) {
                                $documentId = $doc['id'];
                                break 2;
                            }
                        }
                    }
                }
            }

            // Se encontramos o documento (seja no banco ou na busca API), tentamos obter a URL
            if ($documentId) {
                foreach ($urlsToTry as $url) {
                    $responseGet = Http::withHeaders([
                        'X-Api-Key' => $this->apiKey,
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                    ])->get("{$url}/documents/{$documentId}");

                    if ($responseGet->successful()) {
                        $docData = $responseGet->json('data') ?? $responseGet->json();
                        $signingUrl = null;

                        // Busca o link específico do signatário atual na lista de signing_urls
                        $signingUrls = $docData['assignment']['signing_urls'] ?? $docData['signing_urls'] ?? [];

                        foreach ($signingUrls as $sUrl) {
                            if (str_contains(strtolower(urldecode($sUrl['url'] ?? '')), strtolower($emailSignatario))) {
                                $signingUrl = $sUrl['url'];
                                break;
                            }
                        }

                        if ($signingUrl) {
                            Notification::make()->title('Contrato correspondente encontrado no Assinafy. Reaproveitando...')->info()->send();

                            $reqLog = $contrato->assinafy_request_log ?? [];
                            $reqLog['environment_url'] = $url;

                            $contrato->update([
                                'assinafy_id' => $documentId,
                                'assinafy_status' => 'enviado',
                                'assinafy_request_log' => $reqLog,
                            ]);

                            return ['success' => true, 'redirect_url' => $signingUrl];
                        }
                    }
                }

                if ($contrato->assinafy_id) {
                    Notification::make()->title('Atenção: Documento expirado ou link inválido no Assinafy. Gerando novo...')->warning()->send();
                }
            }

            // --- SE NÃO ENCONTRADO: Inicia Fluxo Completo ---
            Notification::make()->title('Novo documento detectado. Iniciando envio...')->info()->send();

            // 1. Gerar PDF
            Notification::make()->title('Gerando PDF do contrato...')->info()->send();

            $template = $contrato->templateContrato
                ?? TemplateContrato::where('is_padrao', true)->first();

            $templateService = app(ContractTemplateService::class);

            $conteudoTemplate = null;
            $cabecalhoTemplate = null;
            $rodapeTemplate = null;

            if ($template) {
                $conteudoTemplate = $templateService->process($contrato, $template->conteudo);
                $cabecalhoTemplate = $template->cabecalho ? $templateService->process($contrato, $template->cabecalho) : null;
                $rodapeTemplate = $template->rodape ? $templateService->process($contrato, $template->rodape) : null;
            }

            $pdfContent = $templateService->generatePdf([
                'contrato' => $contrato,
                'matricula' => $matricula,
                'aluno' => $matricula?->pessoa,
                'responsavel' => $contrato->responsaveisFinanceiros->first()?->pessoa,
                'responsaveisFinanceiros' => $contrato->responsaveisFinanceiros,
                'serie' => $matricula->turma?->serie,
                'curso' => $matricula->turma?->serie?->curso,
                'periodoLetivo' => $matricula->periodoLetivo,
                'conteudo_template' => $conteudoTemplate,
                'cabecalho_template' => $cabecalhoTemplate,
                'rodape_template' => $rodapeTemplate,
            ])->output();

            // --- PASSO 1: Upload do Documento ---
            Notification::make()->title('Passo 1/4: Realizando upload do documento...')->info()->send();

            $responseDoc = Http::withHeaders([
                'X-Api-Key' => $this->apiKey,
            ])->attach(
                'file',
                $pdfContent,
                $nomeArquivoBase
            )->post("{$this->apiUrl}/accounts/{$this->accountId}/documents");

            if (! $responseDoc->successful()) {
                throw new \Exception('Erro no Upload do Documento: '.($responseDoc->json('message') ?? $responseDoc->body()));
            }

            $documentId = $responseDoc->json('id') ?? $responseDoc->json('data.id');

            // --- ETAPA B: Verificar/cadastrar cada signatário no Assinafy ---
            Notification::make()->title('Passo 3/4: Verificando signatários...')->info()->send();

            $signerIds = [];
            $emailToSignerIdMap = [];
            foreach ($signatarios as $signatario) {
                $sigEmail = $signatario['email'];
                $sigNome = $signatario['nome'];
                $sigId = null;

                $responseSearch = Http::withHeaders([
                    'X-Api-Key' => $this->apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])->get("{$this->apiUrl}/accounts/{$this->accountId}/signers", ['search' => $sigEmail]);

                if ($responseSearch->successful()) {
                    foreach ($responseSearch->json('data') ?? [] as $s) {
                        if (isset($s['email']) && strtolower(trim($s['email'])) === strtolower(trim($sigEmail))) {
                            $sigId = $s['id'];
                            Notification::make()->title("Aviso: '{$sigNome}' já existe no Assinafy. Reaproveitando.")->info()->send();
                            break;
                        }
                    }
                }

                if (! $sigId) {
                    Notification::make()->title("Cadastrando signatário: {$sigNome}")->info()->send();

                    $responseSigner = Http::withHeaders([
                        'X-Api-Key' => $this->apiKey,
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                    ])->post("{$this->apiUrl}/accounts/{$this->accountId}/signers", [
                        'full_name' => $sigNome,
                        'email' => $sigEmail,
                    ]);

                    if (! $responseSigner->successful()) {
                        throw new \Exception("Erro ao criar signatário '{$sigNome}': ".($responseSigner->json('message') ?? $responseSigner->body()));
                    }

                    $sigId = $responseSigner->json('id') ?? $responseSigner->json('data.id');
                }

                $signerIds[] = $sigId;
                $emailToSignerIdMap[$sigEmail] = $sigId;
            }

            // Define o signerId do signatário alvo para redirecionamento
            $signerIdAlvo = $emailToSignerIdMap[$emailSignatario] ?? ($signerIds[0] ?? null);

            // --- PASSO 3 (Agora 4): Solicitar Assinatura ---
            Notification::make()->title('Passo 4/4: Vinculando assinatário e disparando e-mail...')->info()->send();

            // --- ESPERA: Aguardar processamento de metadados se necessário ---
            $maxTentativas = 5;
            $tentativa = 0;
            $processado = false;

            while ($tentativa < $maxTentativas && ! $processado) {
                $responseCheck = Http::withHeaders([
                    'X-Api-Key' => $this->apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])->get("{$this->apiUrl}/documents/{$documentId}");

                if ($responseCheck->successful()) {
                    $docDataCheck = $responseCheck->json('data') ?? $responseCheck->json();
                    $checkStatus = $docDataCheck['status'] ?? null;

                    if ($checkStatus !== 'metadata_processing') {
                        $processado = true;
                        break;
                    }
                }

                $tentativa++;
                if (! $processado) {
                    Notification::make()->title("Aguardando processamento do documento no Assinafy (Tentativa {$tentativa}/{$maxTentativas})...")->info()->send();
                    sleep(2); // Aguarda 2 segundos
                }
            }

            // Monta payload com todos os signatários
            $signersPayload = array_map(fn ($id) => ['id' => $id], $signerIds);

            $responseAssign = Http::withHeaders([
                'X-Api-Key' => $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post("{$this->apiUrl}/documents/{$documentId}/assignments", [
                'signers' => $signersPayload,
                'method' => 'virtual',
            ]);

            if ($responseAssign->successful()) {
                $dataAssign = $responseAssign->json();

                // Extração robusta baseada no exemplo do usuário e possíveis variações de env
                $signingUrls = $dataAssign['signing_urls'] ?? $dataAssign['data']['signing_urls'] ?? [];
                $signingUrl = null;

                foreach ($signingUrls as $sUrl) {
                    if (isset($sUrl['signer_id']) && $sUrl['signer_id'] === $signerIdAlvo) {
                        $signingUrl = $sUrl['url'];
                        break;
                    }
                    if (str_contains(strtolower(urldecode($sUrl['url'] ?? '')), strtolower($emailSignatario))) {
                        $signingUrl = $sUrl['url'];
                        break;
                    }
                }

                // Fallback se não achou pelo signer_id ou se o ID for diferente
                $signingUrl = $signingUrl ?? $signingUrls[0]['url'] ?? $dataAssign['data']['signing_url'] ?? $dataAssign['signing_url'] ?? null;

                $contrato->update([
                    'assinafy_id' => $documentId,
                    'assinafy_status' => 'enviado',
                    'assinafy_request_log' => [
                        'document' => $responseDoc->json(),
                        'signer_id' => $signerIdAlvo,
                        'assignment' => $dataAssign,
                    ],
                ]);

                return ['success' => true, 'redirect_url' => $signingUrl];
            }

            $errorMsg = $responseAssign->json('message') ?? $responseAssign->body();
            throw new \Exception('Erro ao solicitar assinatura: '.$errorMsg);
        } catch (\Exception $e) {
            Log::error('Exceção AssinafyService: '.$e->getMessage());

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Obtém o conteúdo do documento assinado na Assinafy com fallback multi-ambiente.
     */
    public function baixarDocumentoAssinado(Contrato $contrato): ?Response
    {
        try {
            if (empty($this->apiKey) || ! $contrato->assinafy_id) {
                return null;
            }

            $urlsToTry = $this->getApiUrlsToTry($contrato);

            foreach ($urlsToTry as $url) {
                $response = Http::withHeaders([
                    'X-Api-Key' => $this->apiKey,
                ])->get("{$url}/documents/{$contrato->assinafy_id}/download/certificated");

                if ($response->successful()) {
                    return $response;
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Exceção ao baixar documento Assinafy: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Processa um webhook do Assinafy.
     *
     * O Assinafy NÃO assina os envelopes dos webhooks; por isso a conclusão das assinaturas só é aceita
     * depois de consultar o documento na API (veja confirmarConclusaoNaApi). Eventos informativos
     * (visualização, verificação de e-mail etc.) apenas enriquecem o histórico e não alteram o status.
     */
    public function handleWebhook(array $payload): bool
    {
        // Conforme documentação: object['id'] contém o ID do documento ($payload['id'] é o ID da atividade)
        $idAssinafy = $payload['object']['id'] ?? $payload['document_id'] ?? null;
        $event = $payload['event'] ?? null;
        $fileName = $payload['object']['name'] ?? null;
        Log::info('Processando webhook Assinafy', compact('idAssinafy', 'event', 'fileName'));

        if (! $idAssinafy) {
            return false;
        }

        $eventLower = strtolower((string) $event);
        $status = self::EVENTO_PARA_STATUS[$eventLower] ?? null;

        $contrato = $this->localizarContrato((string) $idAssinafy, $fileName);

        if (! $contrato) {
            return false;
        }

        $requestLog = $this->registrarEventoNoHistorico($contrato, $payload, $eventLower);

        // Evento informativo: só o histórico é atualizado
        if ($status === null) {
            $contrato->update(['assinafy_id' => $idAssinafy, 'assinafy_request_log' => $requestLog]);

            return true;
        }

        $concluidoEm = null;

        if (in_array($status, Contrato::STATUS_ASSINADO, true)) {
            $confirmacao = $this->confirmarConclusaoNaApi($contrato);

            if (! $confirmacao['confirmado']) {
                Log::warning('Webhook Assinafy: conclusão não confirmada pela API; o status será reconciliado depois.', [
                    'contrato_id' => $contrato->id,
                    'event' => $event,
                ]);
                $contrato->update(['assinafy_id' => $idAssinafy, 'assinafy_request_log' => $requestLog]);

                return true;
            }

            $status = $confirmacao['status'] ?? $status;
            $concluidoEm = $this->momentoDoEvento($payload);
        }

        $contrato->assinafy_id = $idAssinafy;
        $this->aplicarStatus($contrato, $status, $requestLog, $concluidoEm);

        return true;
    }

    /**
     * Localiza o contrato pelo ID do documento no Assinafy ou, como alternativa, pelo número no nome do arquivo.
     */
    private function localizarContrato(string $idAssinafy, ?string $fileName): ?Contrato
    {
        $contrato = Contrato::where('assinafy_id', $idAssinafy)->first();

        // Fallback: se não achar pelo assinafy_id, tenta extrair ID do nome do arquivo (ex: Contrato - Escola Torre de Marfim - Aluno - 136.pdf)
        if (! $contrato && $fileName && preg_match('/Contrato - Escola Torre de Marfim - .*? - (\d+)\.pdf/i', $fileName, $matches)) {
            $contrato = Contrato::find($matches[1]);
        }

        return $contrato;
    }

    /**
     * Grava o último webhook e o status individual do signatário (quando o evento é de assinatura/recusa).
     *
     * @return array<string, mixed> histórico atualizado (ainda não salvo)
     */
    private function registrarEventoNoHistorico(Contrato $contrato, array $payload, string $evento): array
    {
        $requestLog = $contrato->assinafy_request_log ?? [];

        $emailDoSignatario = $payload['subject']['email']
            ?? $payload['object']['signer']['email']
            ?? $payload['signer']['email']
            ?? $payload['payload']['signer_email']
            ?? $payload['email']
            ?? null;

        $statusDoSignatario = match ($evento) {
            'signer_signed_document', 'signer_signed' => 'signed',
            'signer_rejected_document' => 'refused',
            default => null,
        };

        if ($emailDoSignatario && $statusDoSignatario) {
            $emailLimpo = strtolower(trim($emailDoSignatario));
            $requestLog['signers_status'][$emailLimpo] = [
                'status' => $statusDoSignatario,
                'signed_at' => $this->momentoDoEvento($payload)->toDateTimeString(),
            ];
        }

        $requestLog['webhook_last'] = $payload;

        return $requestLog;
    }

    /**
     * Confirma, consultando o documento na API, que todas as assinaturas foram coletadas e devolve a etapa real
     * (ready, certificating ou certificated). Sem credenciais da API (ambiente sem integração) o payload é aceito.
     *
     * @return array{confirmado: bool, status: ?string}
     */
    private function confirmarConclusaoNaApi(Contrato $contrato): array
    {
        if (empty($this->apiKey)) {
            return ['confirmado' => true, 'status' => null];
        }

        try {
            $consulta = $this->consultarDocumento($contrato);
        } catch (\Throwable $e) {
            Log::error("Falha ao confirmar assinatura no Assinafy para Contrato #{$contrato->id}: ".$e->getMessage());

            return ['confirmado' => false, 'status' => null];
        }

        $statusDoContrato = $consulta['ok']
            ? (self::STATUS_API_PARA_STATUS[$consulta['dados']['status'] ?? ''] ?? null)
            : null;

        if ($statusDoContrato && in_array($statusDoContrato, Contrato::STATUS_ASSINADO, true)) {
            return ['confirmado' => true, 'status' => $statusDoContrato];
        }

        return ['confirmado' => false, 'status' => null];
    }

    /**
     * Momento do evento (campo created_at do envelope, em segundos Unix) ou, se ausente, agora.
     */
    private function momentoDoEvento(array $payload): Carbon
    {
        $criadoEm = $payload['created_at'] ?? null;

        return is_numeric($criadoEm) && (int) $criadoEm > 0
            ? Carbon::createFromTimestamp((int) $criadoEm, config('app.timezone'))
            : now();
    }

    /**
     * Aplica o novo status ao contrato respeitando a ordem das etapas (um contrato assinado nunca "volta")
     * e define data_aceite como o momento em que a última assinatura foi coletada.
     *
     * @param  array<string, mixed>  $requestLog  histórico já atualizado, gravado junto
     * @param  Carbon|null  $concluidoEm  quando todas as assinaturas foram coletadas (se conhecido)
     * @param  bool  $sincronizarAceite  também corrige data_aceite de um contrato já assinado, usando $concluidoEm
     */
    private function aplicarStatus(Contrato $contrato, string $novoStatus, array $requestLog, ?Carbon $concluidoEm = null, bool $sincronizarAceite = false): void
    {
        $statusAtual = $contrato->assinafy_status;
        $assinadoAntes = in_array($statusAtual, Contrato::STATUS_ASSINADO, true);
        $dados = ['assinafy_request_log' => $requestLog];

        if ($this->etapaPermitida($statusAtual, $novoStatus)) {
            $dados['assinafy_status'] = $novoStatus;

            if (! $assinadoAntes && in_array($novoStatus, Contrato::STATUS_ASSINADO, true)) {
                $dados['data_aceite'] = $concluidoEm ?? now();
            }
        }

        $statusFinal = $dados['assinafy_status'] ?? $statusAtual;
        $assinadoAgora = in_array($statusFinal, Contrato::STATUS_ASSINADO, true);

        if ($sincronizarAceite && $concluidoEm && $assinadoAgora) {
            $dados['data_aceite'] = $concluidoEm;
        }

        $contrato->update($dados);

        // Se este contrato é de uma Rematrícula Online aguardando assinatura, a
        // confirmação do documento assinado é o que efetivamente conclui o processo.
        if ($assinadoAgora) {
            $this->confirmarRematricula($contrato);
        }
    }

    /**
     * Regra de progressão: antes da conclusão qualquer status pode ser aplicado; depois, só etapas mais avançadas.
     */
    private function etapaPermitida(?string $statusAtual, string $novoStatus): bool
    {
        if ($statusAtual === $novoStatus) {
            return false;
        }

        if (in_array($statusAtual, Contrato::STATUS_ASSINADO, true)) {
            return (self::ORDEM_ETAPAS[$novoStatus] ?? -1) > (self::ORDEM_ETAPAS[$statusAtual] ?? PHP_INT_MAX);
        }

        return true;
    }

    private function confirmarRematricula(Contrato $contrato): void
    {
        // Confirma a rematrícula e ativa a nova matrícula (que aguardava a assinatura, como Pendente).
        // Uma rematrícula cancelada não é reativada por uma assinatura tardia.
        $contrato->rematricula?->confirmarPelaAssinatura();
    }

    /**
     * Consulta o documento na API do Assinafy (tentando os ambientes configurados e o fallback).
     *
     * @return array{ok: bool, dados: array<string, mixed>, url: ?string, erro: ?string}
     */
    private function consultarDocumento(Contrato $contrato): array
    {
        $ultimoErro = null;

        foreach ($this->getApiUrlsToTry($contrato) as $url) {
            $res = Http::withHeaders([
                'X-Api-Key' => $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->get("{$url}/documents/{$contrato->assinafy_id}");

            if ($res->successful()) {
                return ['ok' => true, 'dados' => $res->json('data') ?? $res->json() ?? [], 'url' => $url, 'erro' => null];
            }

            $ultimoErro = $res->json('message') ?? $res->body();
        }

        return [
            'ok' => false,
            'dados' => [],
            'url' => null,
            'erro' => $ultimoErro ?? 'Documento não encontrado nos ambientes da Assinafy.',
        ];
    }

    /**
     * Consulta o documento na API do Assinafy e atualiza o status do contrato (ready, certificating, certificated...)
     * e os status individuais dos signatários, com fallback multi-ambiente.
     */
    public function consultarEAtualizarStatusSignatarios(Contrato $contrato): array
    {
        if (empty($this->apiKey) || ! $contrato->assinafy_id) {
            return [
                'success' => false,
                'message' => 'Contrato ainda não possui documento gerado na Assinafy ou API key não configurada.',
            ];
        }

        try {
            $consulta = $this->consultarDocumento($contrato);

            if (! $consulta['ok']) {
                return [
                    'success' => false,
                    'message' => 'Erro ao consultar documento no Assinafy: '.$consulta['erro'],
                ];
            }

            $docData = $consulta['dados'];
            $docStatus = $docData['status'] ?? null;

            $signersStatus = [];

            // Tenta obter de 'assignment.signers', 'assignment.summary.signers', 'signers' ou 'assignment.signing_urls'
            $signersList = $docData['assignment']['signers']
                ?? $docData['assignment']['summary']['signers']
                ?? $docData['signers']
                ?? $docData['assignment']['signing_urls']
                ?? [];

            foreach ($signersList as $s) {
                $email = null;
                if (! empty($s['email'])) {
                    $email = strtolower(trim($s['email']));
                } elseif (! empty($s['url'])) {
                    $parsedUrl = parse_url($s['url']);
                    if (isset($parsedUrl['query'])) {
                        parse_str($parsedUrl['query'], $queryVars);
                        if (! empty($queryVars['email'])) {
                            $email = strtolower(trim($queryVars['email']));
                        }
                    }
                }

                if ($email) {
                    $statusValue = strtolower((string) ($s['status'] ?? ''));
                    $isCompleted = ($s['completed'] ?? false) === true
                        || ($s['signed'] ?? false) === true
                        || in_array($statusValue, ['signed', 'completed', 'signer_signed_document', 'signer_signed']);

                    $isRefused = in_array($statusValue, ['refused', 'rejected', 'declined']);

                    $sigStatus = $isCompleted ? 'signed' : ($isRefused ? 'refused' : 'pending');
                    $signedAt = $s['signed_at'] ?? $s['completed_at'] ?? null;

                    $signersStatus[$email] = [
                        'status' => $sigStatus,
                        'signed_at' => $signedAt,
                    ];
                }
            }

            $requestLog = $contrato->assinafy_request_log ?? [];
            $requestLog['signers_status'] = array_merge($requestLog['signers_status'] ?? [], $signersStatus);
            $requestLog['last_check'] = now()->toDateTimeString();
            $requestLog['api_status'] = $docStatus;
            if ($consulta['url']) {
                $requestLog['environment_url'] = $consulta['url'];
            }

            $novoStatus = self::STATUS_API_PARA_STATUS[$docStatus ?? ''] ?? null;

            if ($novoStatus) {
                $concluidoEm = in_array($novoStatus, Contrato::STATUS_ASSINADO, true)
                    ? $this->dataDaUltimaAssinatura($signersStatus)
                    : null;

                $this->aplicarStatus($contrato, $novoStatus, $requestLog, $concluidoEm, sincronizarAceite: true);
            } else {
                $contrato->update(['assinafy_request_log' => $requestLog]);
            }

            return [
                'success' => true,
                'status' => $docStatus,
                'signers' => $signersStatus,
            ];
        } catch (\Exception $e) {
            Log::error("Exceção ao consultar status Assinafy para Contrato #{$contrato->id}: ".$e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Data da última assinatura entre os signatários que já assinaram (nulo se nenhuma data estiver disponível).
     *
     * @param  array<string, array{status?: string, signed_at?: mixed}>  $signersStatus
     */
    private function dataDaUltimaAssinatura(array $signersStatus): ?Carbon
    {
        $datas = collect($signersStatus)
            ->filter(fn (array $signatario): bool => ($signatario['status'] ?? null) === 'signed')
            ->map(fn (array $signatario): ?Carbon => $this->interpretarData($signatario['signed_at'] ?? null))
            ->filter();

        return $datas->isEmpty() ? null : $datas->max();
    }

    private function interpretarData(mixed $valor): ?Carbon
    {
        if (blank($valor)) {
            return null;
        }

        try {
            $data = is_numeric($valor) ? Carbon::createFromTimestamp((int) $valor) : Carbon::parse($valor);

            return $data->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Retorna a lista de URLs da API Assinafy a serem tentadas (Primária e Fallback entre Produção e Sandbox).
     */
    public function getApiUrlsToTry(?Contrato $contrato = null): array
    {
        $urls = [];

        // 1. Se o contrato tiver uma URL de ambiente salva no log
        $savedUrl = $contrato?->assinafy_request_log['environment_url'] ?? null;
        if (! empty($savedUrl)) {
            $urls[] = rtrim($savedUrl, '/');
        }

        // 2. A URL configurada no ambiente atual
        $configuredUrl = rtrim($this->apiUrl, '/');
        if (! in_array($configuredUrl, $urls)) {
            $urls[] = $configuredUrl;
        }

        // 3. Fallbacks para o outro ambiente (Sandbox vs Produção)
        $sandBoxUrl = 'https://sandbox.assinafy.com.br/v1';
        $prodUrl = 'https://api.assinafy.com.br/v1';

        if (! in_array($sandBoxUrl, $urls)) {
            $urls[] = $sandBoxUrl;
        }
        if (! in_array($prodUrl, $urls)) {
            $urls[] = $prodUrl;
        }

        return $urls;
    }
}

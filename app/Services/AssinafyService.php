<?php

namespace App\Services;

use App\Models\Contrato;
use App\Models\TemplateContrato;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Notifications\Notification;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AssinafyService
{
    protected string $apiUrl;

    protected ?string $apiKey = null;

    protected ?string $accountId = null;

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

    public function handleWebhook(array $payload): bool
    {
        Log::info('Payload: '.json_encode($payload));
        // Conforme documentação: object['id'] contém o ID do documento
        $idAssinafy = $payload['object']['id'] ?? $payload['document_id'] ?? $payload['id'] ?? null;
        $event = $payload['event'] ?? null;
        $fileName = $payload['object']['name'] ?? null;
        Log::info('idAssinafy: '.$idAssinafy);
        Log::info('event: '.$event);
        Log::info('fileName: '.$fileName);

        // Mapeia eventos para status do contrato
        $eventLower = strtolower((string) $event);

        $status = match ($eventLower) {
            'document_signed', 'document_completed' => 'signed',
            'signer_signed_document', 'signer_signed', 'signature_completed' => 'enviado',
            'document_refused' => 'refused',
            'document_ready' => 'ready',
            'document_uploaded', 'signature_requested' => 'enviado',
            default => $event ?? 'unknown'
        };

        if (! $idAssinafy || ! $status) {
            return false;
        }

        $contrato = Contrato::where('assinafy_id', $idAssinafy)->first();

        // Fallback: se não achar pelo assinafy_id, tenta extrair ID do nome do arquivo (ex: Contrato - Escola Torre de Marfim - Aluno - 136.pdf)
        if (! $contrato && $fileName && preg_match('/Contrato - Escola Torre de Marfim - .*? - (\d+)\.pdf/i', $fileName, $matches)) {
            $contrato = Contrato::find($matches[1]);
        }

        if ($contrato) {
            $requestLog = $contrato->assinafy_request_log ?? [];
            $signerEmail = $payload['object']['signer']['email']
                ?? $payload['signer']['email']
                ?? $payload['email']
                ?? null;

            if ($signerEmail) {
                $signerEmailClean = strtolower(trim($signerEmail));
                $signersStatus = $requestLog['signers_status'] ?? [];

                // Se o evento é de um signatário individual assinando, grava como 'signed' para aquele signatário
                $isSignerSignedEvent = in_array($eventLower, ['signer_signed_document', 'signer_signed', 'document_signed', 'document_completed']);
                $signersStatus[$signerEmailClean] = [
                    'status' => $isSignerSignedEvent ? 'signed' : (in_array($status, ['signed', 'completed']) ? 'signed' : $status),
                    'signed_at' => now()->toDateTimeString(),
                ];
                $requestLog['signers_status'] = $signersStatus;
            }

            $requestLog['webhook_last'] = $payload;

            $updateData = [
                'assinafy_id' => $idAssinafy,
                'assinafy_status' => $status,
                'assinafy_request_log' => $requestLog,
            ];

            if ($status === 'signed' || $status === 'completed') {
                $updateData['data_aceite'] = now();
            }

            $contrato->update($updateData);

            return true;
        }

        return false;
    }

    /**
     * Consulta o documento na API da Assinafy e atualiza os status individuais dos signatários no contrato com fallback multi-ambiente.
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
            $urlsToTry = $this->getApiUrlsToTry($contrato);
            $response = null;
            $usedUrl = null;

            foreach ($urlsToTry as $url) {
                $res = Http::withHeaders([
                    'X-Api-Key' => $this->apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])->get("{$url}/documents/{$contrato->assinafy_id}");

                if ($res->successful()) {
                    $response = $res;
                    $usedUrl = $url;
                    break;
                }
            }

            if (! $response || ! $response->successful()) {
                $lastErr = $response ? ($response->json('message') ?? $response->body()) : 'Documento não encontrado nos ambientes da Assinafy.';

                return [
                    'success' => false,
                    'message' => 'Erro ao consultar documento no Assinafy: '.$lastErr,
                ];
            }

            $docData = $response->json('data') ?? $response->json();
            $docStatus = $docData['status'] ?? $contrato->assinafy_status;

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
            if ($usedUrl) {
                $requestLog['environment_url'] = $usedUrl;
            }

            $updateData = [
                'assinafy_request_log' => $requestLog,
            ];

            if ($docStatus === 'signed' || $docStatus === 'completed') {
                $updateData['assinafy_status'] = $docStatus;
                if (! $contrato->data_aceite) {
                    $updateData['data_aceite'] = now();
                }
            }

            $contrato->update($updateData);

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

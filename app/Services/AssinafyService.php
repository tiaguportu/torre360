<?php

namespace App\Services;

use App\Models\Contrato;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Notifications\Notification;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AssinafyService
{
    protected string $apiUrl;

    protected string $apiKey;

    protected string $accountId;

    public function __construct()
    {
        $this->apiUrl = rtrim(config('services.assinafy.url', env('ASSINAFY_API_URL')), '/');
        $this->apiKey = config('services.assinafy.key', env('ASSINAFY_API_KEY'));
        $this->accountId = config('services.assinafy.account_id', env('ASSINAFY_ACCOUNT_ID'));

        // Ajuste Crítico: Para chamadas de API no Sandbox, a URL deve ser sandbox.assinafy.com.br
        // O endereço .pages.dev é apenas o frontend e retorna 405 para POSTs.
        if (str_contains($this->apiUrl, 'assinafy-app.pages.dev')) {
            $this->apiUrl = 'https://sandbox.assinafy.com.br/v1';
        }

        // Garante que a URL tenha o sufixo /v1 se necessário (caso venha da config sem ele)
        if (!str_contains($this->apiUrl, '/v1')) {
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
            // 0. Carregar dados relacionados
            $contrato->load([
                'matriculas.pessoa',
                'matriculas.turma.serie.curso',
                'matriculas.periodoLetivo',
                'responsaveisFinanceiros.pessoa.users',
            ]);

            $matriculas = $contrato->matriculas;
            $matricula = $matriculas->first();

            if (!$matricula) {
                return ['success' => false, 'message' => "Contrato #{$contrato->id} não possui matrículas vinculadas."];
            }

            // Coleta todos os signatários: usuários vinculados a cada responsável financeiro
            $signatarios = collect();
            foreach ($contrato->responsaveisFinanceiros as $resp) {
                $pessoa = $resp->pessoa;
                if (!$pessoa) {
                    continue;
                }

                foreach ($pessoa->users as $user) {
                    if ($user->email) {
                        $signatarios->push([
                            'nome' => $user->name ?? $pessoa->nome,
                            'email' => $user->email,
                        ]);
                    }
                }
            }

            // Fallback: se não houver nenhum signatário via usuário, usa e-mail do aluno
            if ($signatarios->isEmpty()) {
                $aluno = $matricula->pessoa;
                $signatarios->push([
                    'nome' => $aluno?->nome ?? 'Aluno',
                    'email' => $aluno?->email ?? '',
                ]);
            }

            // Remove duplicados por e-mail
            $signatarios = $signatarios->unique('email')->values();

            // Para compatibilidade na busca do documento
            $emailSignatario = $signatarios->first()['email'];
            $nomeSignatario = $signatarios->first()['nome'];

            $nomeArquivoBase = "contrato_{$contrato->id}.pdf";

            // --- ETAPA A: Verificar se o documento já existe no Assinafy (Consulta API) ---
            Notification::make()->title('Consultando Assinafy para evitar duplicidade de documento...')->info()->send();

            $documentId = $contrato->assinafy_id;

            // Busca por nome do arquivo via API
            $responseSearchDoc = Http::withHeaders([
                'X-Api-Key' => $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->get("{$this->apiUrl}/accounts/{$this->accountId}/documents", [
                        'search' => $nomeArquivoBase,
                    ]);

            if ($responseSearchDoc->successful()) {
                $documents = $responseSearchDoc->json('data') ?? [];
                foreach ($documents as $doc) {
                    if (($doc['name'] ?? '') === $nomeArquivoBase || ($doc['original_name'] ?? '') === $nomeArquivoBase) {
                        $documentId = $doc['id'];
                        break;
                    }
                }
            }

            // Se encontramos o documento (seja no banco ou na busca API), tentamos obter a URL
            if ($documentId) {
                $responseGet = Http::withHeaders([
                    'X-Api-Key' => $this->apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])->get("{$this->apiUrl}/documents/{$documentId}");

                if ($responseGet->successful()) {
                    $docData = $responseGet->json('data');
                    $signingUrl = null;

                    // Busca o link específico do signatário atual na lista de signing_urls
                    $signingUrls = $docData['assignment']['signing_urls'] ?? $docData['signing_urls'] ?? [];
                    $signingUrl = null;

                    foreach ($signingUrls as $sUrl) {
                        // Tenta casar pelo e-mail na URL ou pelo signer_id se disponível
                        if (str_contains($sUrl['url'] ?? '', $emailSignatario)) {
                            $signingUrl = $sUrl['url'];
                            break;
                        }
                    }

                    // Fallback para a primeira URL se não achou específica
                    $signingUrl = $signingUrl ?? $signingUrls[0]['url'] ?? $docData['signing_url'] ?? null;

                    if ($signingUrl) {
                        Notification::make()->title('Contrato correspondente encontrado no Assinafy. Reaproveitando...')->info()->send();

                        if ($contrato->assinafy_id !== $documentId) {
                            $contrato->update(['assinafy_id' => $documentId, 'assinafy_status' => 'enviado']);
                        }

                        return ['success' => true, 'redirect_url' => $signingUrl];
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
            $pdfContent = Pdf::loadView('pdfs.contrato', [
                'contrato' => $contrato,
                'matriculas' => $matriculas,
                'matricula' => $matricula,
                'responsaveisFinanceiros' => $contrato->responsaveisFinanceiros,
                'serie' => $matricula->turma?->serie,
                'curso' => $matricula->turma?->serie?->curso,
                'periodoLetivo' => $matricula->periodoLetivo,
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

            if (!$responseDoc->successful()) {
                throw new \Exception('Erro no Upload do Documento: ' . ($responseDoc->json('message') ?? $responseDoc->body()));
            }

            $documentId = $responseDoc->json('id') ?? $responseDoc->json('data.id');

            // --- NOVO: Preparar Documento (Sem campos manuais) ---
            Notification::make()->title('Passo 2/4: Preparando documento para assinar...')->info()->send();

            $responsePrepare = Http::withHeaders([
                'X-Api-Key' => $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post("{$this->apiUrl}/documents/{$documentId}/prepare", [
                        'status' => 'prepared',
                    ]);

            if (!$responsePrepare->successful()) {
                Log::warning('Aviso ao preparar: ' . $responsePrepare->body());
            }

            // --- ETAPA B: Verificar/cadastrar cada signatário no Assinafy ---
            Notification::make()->title('Passo 3/4: Verificando signatários...')->info()->send();

            $signerIds = [];
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
                        if (isset($s['email']) && $s['email'] === $sigEmail) {
                            $sigId = $s['id'];
                            Notification::make()->title("Aviso: '{$sigNome}' já existe no Assinafy. Reaproveitando.")->info()->send();
                            break;
                        }
                    }
                }

                if (!$sigId) {
                    Notification::make()->title("Cadastrando signatário: {$sigNome}")->info()->send();

                    $responseSigner = Http::withHeaders([
                        'X-Api-Key' => $this->apiKey,
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                    ])->post("{$this->apiUrl}/accounts/{$this->accountId}/signers", [
                                'full_name' => $sigNome,
                                'email' => $sigEmail,
                            ]);

                    if (!$responseSigner->successful()) {
                        throw new \Exception("Erro ao criar signatário '{$sigNome}': " . ($responseSigner->json('message') ?? $responseSigner->body()));
                    }

                    $sigId = $responseSigner->json('id') ?? $responseSigner->json('data.id');
                }

                $signerIds[] = $sigId;
            }

            // Primeiro ID para fallback de URL de assinatura
            $signerId = $signerIds[0] ?? null;

            // --- PASSO 3 (Agora 4): Solicitar Assinatura ---
            Notification::make()->title('Passo 4/4: Vinculando assinatário e disparando e-mail...')->info()->send();

            // --- ESPERA: Aguardar processamento de metadados se necessário ---
            $maxTentativas = 5;
            $tentativa = 0;
            $processado = false;

            while ($tentativa < $maxTentativas && !$processado) {
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
                if (!$processado) {
                    Notification::make()->title("Aguardando processamento do documento no Assinafy (Tentativa {$tentativa}/{$maxTentativas})...")->info()->send();
                    sleep(2); // Aguarda 2 segundos
                }
            }

            // Monta payload com todos os signatários
            $signersPayload = array_map(fn($id) => ['id' => $id], $signerIds);

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
                    if (isset($sUrl['signer_id']) && $sUrl['signer_id'] === $signerId) {
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
                        'signer_id' => $signerId,
                        'assignment' => $dataAssign,
                    ],
                ]);

                return ['success' => true, 'redirect_url' => $signingUrl];
            }

            $errorMsg = $responseAssign->json('message') ?? $responseAssign->body();
            throw new \Exception('Erro ao solicitar assinatura: ' . $errorMsg);
        } catch (\Exception $e) {
            Log::error('Exceção AssinafyService: ' . $e->getMessage());

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Obtém o conteúdo do documento assinado na Assinafy.
     */
    public function baixarDocumentoAssinado(Contrato $contrato): ?Response
    {
        try {
            if (!$contrato->assinafy_id) {
                return null;
            }

            $response = Http::withHeaders([
                'X-Api-Key' => $this->apiKey,
            ])->get("{$this->apiUrl}/documents/{$contrato->assinafy_id}/download/certificated");

            if ($response->successful()) {
                return $response;
            }

            Log::warning("Erro ao baixar documento Assinafy para Contrato #{$contrato->id}: " . $response->body());

            return null;
        } catch (\Exception $e) {
            Log::error('Exceção ao baixar documento Assinafy: ' . $e->getMessage());

            return null;
        }
    }

    public function handleWebhook(array $payload): bool
    {
        Log::info('Payload: ' . json_encode($payload));
        // Conforme documentação: object['id'] contém o ID do documento
        $idAssinafy = $payload['object']['id'] ?? $payload['document_id'] ?? $payload['id'] ?? null;
        $event = $payload['event'] ?? null;
        $fileName = $payload['object']['name'] ?? null;
        Log::info('idAssinafy: ' . $idAssinafy);
        Log::info('event: ' . $event);
        Log::info('fileName: ' . $fileName);


        // Mapeia eventos para status do contrato
        $status = match ($event) {
            'document_signed' => 'signed',
            'document_completed' => 'completed',
            'document_refused' => 'refused',
            'document_ready' => 'ready',
            'document_uploaded' => 'enviado',
            'signature_requested' => 'enviado',
            default => $event ?? 'unknown'
        };

        if (!$idAssinafy || !$status) {
            return false;
        }

        $contrato = Contrato::where('assinafy_id', $idAssinafy)->first();

        // Fallback: se não achar pelo assinafy_id, tenta extrair ID do nome do arquivo (ex: contrato_136.pdf)
        if (!$contrato && $fileName && preg_match('/contrato_(\d+)/', $fileName, $matches)) {
            $contrato = Contrato::find($matches[1]);
            if ($contrato && !$contrato->assinafy_id) {
                $contrato->update(['assinafy_id' => $idAssinafy]);
            }
        }

        if ($contrato) {
            $contrato->update([
                'assinafy_status' => $status,
                'assinafy_request_log' => array_merge($contrato->assinafy_request_log ?? [], ['webhook_last' => $payload]),
            ]);

            if ($status === 'signed' || $status === 'completed') {
                $contrato->update(['data_aceite' => now()]);
            }

            return true;
        }

        return false;
    }
}

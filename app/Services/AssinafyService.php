<?php

namespace App\Services;

use App\Models\Contrato;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Notifications\Notification;
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
     */
    public function enviarContrato(Contrato $contrato): array
    {
        try {
            // 0. Carregar dados relacionados
            $contrato->load([
                'matriculas.pessoa', 
                'matriculas.turma.serie.curso', 
                'matriculas.periodoLetivo',
                'responsaveisFinanceiros.pessoa.users'
            ]);
            $matricula = $contrato->matriculas->first();

            if (!$matricula) {
                return ['success' => false, 'message' => "Contrato #{$contrato->id} não possui matrículas vinculadas."];
            }

            $aluno = $matricula->pessoa;
            $responsavel = $contrato->responsaveisFinanceiros->first()?->pessoa;
            $responsavelUser = $responsavel?->users->first();

            $nomeSignatario = $responsavel?->nome ?? $responsavelUser?->name ?? $aluno?->nome;
            $emailSignatario = $responsavel?->email ?? $responsavelUser?->email ?? $aluno?->email;

            $nomeArquivoBase = "Contrato_Matricula_{$matricula->id}-1.pdf";

            // --- ETAPA A: Verificar se o documento já existe no Assinafy (Consulta API) ---
            Notification::make()->title('Consultando Assinafy para evitar duplicidade de documento...')->info()->send();

            $documentId = $contrato->assinafy_id;

            // Busca por nome do arquivo via API
            $responseSearchDoc = Http::withHeaders([
                'X-Api-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ])->get("{$this->apiUrl}/accounts/{$this->accountId}/documents", [
                        'search' => $nomeArquivoBase
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
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/json',
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
                'matricula' => $matricula,
                'aluno' => $aluno,
                'responsavel' => $responsavel,
                'serie' => $matricula->turma?->serie,
                'curso' => $matricula->turma?->serie?->curso,
                'periodoLetivo' => $matricula->periodoLetivo,
            ])->output();

            // --- PASSO 1: Upload do Documento ---
            Notification::make()->title('Passo 1/4: Realizando upload do documento...')->info()->send();

            $responseDoc = Http::withHeaders([
                'X-Api-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ])->attach(
                    'file',
                    $pdfContent,
                    "Contrato_Matricula_{$matricula->id}.pdf"
                )->post("{$this->apiUrl}/accounts/{$this->accountId}/documents");

            if (!$responseDoc->successful()) {
                throw new \Exception("Erro no Upload do Documento: " . ($responseDoc->json('message') ?? $responseDoc->body()));
            }

            $documentId = $responseDoc->json('id') ?? $responseDoc->json('data.id');

            // --- NOVO: Preparar Documento (Sem campos manuais) ---
            Notification::make()->title('Passo 2/4: Preparando documento para assinar...')->info()->send();

            $responsePrepare = Http::withHeaders([
                'X-Api-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ])->post("{$this->apiUrl}/documents/{$documentId}/prepare", [
                        'status' => 'prepared'
                    ]);

            if (!$responsePrepare->successful()) {
                Log::warning('Aviso ao preparar: ' . $responsePrepare->body());
            }

            // --- ETAPA B: Verificar se o usuário (signer) já foi cadastrado no Assinafy ---
            Notification::make()->title('Passo 3/4: Verificando signatário...')->info()->send();

            $signerId = null;
            $responseSearch = Http::withHeaders([
                'X-Api-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ])->get("{$this->apiUrl}/accounts/{$this->accountId}/signers", ['search' => $emailSignatario]);
            
            if ($responseSearch->successful()) {
                $signers = $responseSearch->json('data') ?? [];
                foreach ($signers as $s) {
                    if (isset($s['email']) && $s['email'] === $emailSignatario) {
                        $signerId = $s['id'];
                        Notification::make()->title("Aviso: O usuário '{$nomeSignatario}' já existe no Assinafy. Reaproveitando cadastro.")->info()->send();
                        break;
                    }
                }
            }

            if (!$signerId) {
                Notification::make()->title("Cadastrando novo signatário: {$nomeSignatario}")->info()->send();

                $responseSigner = Http::withHeaders([
                    'X-Api-Key' => $this->apiKey,
                    'Accept' => 'application/json',
                ])->post("{$this->apiUrl}/accounts/{$this->accountId}/signers", [
                            'full_name' => $nomeSignatario,
                            'email' => $emailSignatario,
                        ]);

                if (!$responseSigner->successful()) {
                    throw new \Exception("Erro ao criar signatário: " . ($responseSigner->json('message') ?? $responseSigner->body()));
                }

                $signerId = $responseSigner->json('id') ?? $responseSigner->json('data.id');
            }

            // --- PASSO 3 (Agora 4): Solicitar Assinatura ---
            Notification::make()->title('Passo 4/4: Vinculando assinatário e disparando e-mail...')->info()->send();

            $responseAssign = Http::withHeaders([
                'X-Api-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ])->post("{$this->apiUrl}/documents/{$documentId}/assignments", [
                        'signers' => [['id' => $signerId]],
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
            throw new \Exception("Erro ao solicitar assinatura: " . $errorMsg);

        } catch (\Exception $e) {
            Log::error('Exceção AssinafyService: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Obtém o conteúdo do documento assinado na Assinafy.
     */
    public function baixarDocumentoAssinado(Contrato $contrato): ?\Illuminate\Http\Client\Response
    {
        try {
            if (!$contrato->assinafy_id) {
                return null;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->get("{$this->apiUrl}/documents/{$contrato->assinafy_id}/download/certificated");

            if ($response->successful()) {
                return $response;
            }

            Log::warning("Erro ao baixar documento Assinafy para Contrato #{$contrato->id}: " . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error("Exceção ao baixar documento Assinafy: " . $e->getMessage());
            return null;
        }
    }

    public function handleWebhook(array $payload): bool
    {
        $idAssinafy = $payload['document_id'] ?? $payload['id'] ?? null;
        $status = $payload['status'] ?? null;

        if (!$idAssinafy || !$status) {
            return false;
        }

        $contrato = Contrato::where('assinafy_id', $idAssinafy)->first();

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

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
            $contrato->load(['matriculas.pessoa', 'matriculas.turma.serie.curso', 'matriculas.periodoLetivo']);
            $matricula = $contrato->matriculas->first();

            if (!$matricula) {
                return ['success' => false, 'message' => "Contrato #{$contrato->id} não possui matrículas vinculadas."];
            }

            $aluno = $matricula->pessoa;
            $responsavel = $contrato->responsaveisFinanceiros->first()?->pessoa;

            Notification::make()->title('Gerando PDF do contrato...')->info()->send();

            // 1. Gerar PDF temporário
            $pdfContent = Pdf::loadView('pdfs.contrato', [
                'contrato' => $contrato,
                'matricula' => $matricula,
                'aluno' => $aluno,
                'responsavel' => $responsavel,
                'serie' => $matricula->turma?->serie,
                'curso' => $matricula->turma?->serie?->curso,
                'periodoLetivo' => $matricula->periodoLetivo,
            ])->output();

            // --- PASSO 1: Upload do Documento (Multipart) ---
            Notification::make()->title('Passo 1/3: Realizando upload do documento...')->info()->send();
            
            $responseDoc = Http::withHeaders([
                'X-Api-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ])->attach(
                'file', $pdfContent, "Contrato_Matricula_{$matricula->id}.pdf"
            )->post("{$this->apiUrl}/accounts/{$this->accountId}/documents");

            if (!$responseDoc->successful()) {
                throw new \Exception("Erro no Upload do Documento (Status {$responseDoc->status()}): " . ($responseDoc->json('message') ?? $responseDoc->body()));
            }

            $documentId = $responseDoc->json('id') ?? $responseDoc->json('data.id');
            if (!$documentId) {
                throw new \Exception("ID do documento não retornado no upload.");
            }

            // --- PASSO 2: Criar Signatário ---
            Notification::make()->title('Passo 2/3: Configurando signatário...')->info()->send();
            
            $nomeSignatario = $responsavel?->nome ?? $aluno?->nome;
            $emailSignatario = $responsavel?->email ?? $aluno?->email;

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
            if (!$signerId) {
                throw new \Exception("ID do signatário não retornado.");
            }

            // --- PASSO 3: Solicitar Assinatura (Assignment) ---
            Notification::make()->title('Passo 3/3: Enviando solicitação de assinatura...')->info()->send();
            
            $responseAssign = Http::withHeaders([
                'X-Api-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ])->post("{$this->apiUrl}/documents/{$documentId}/assignments", [
                'method' => 'virtual',
                'signerIds' => [$signerId],
            ]);

            if ($responseAssign->successful()) {
                $contrato->update([
                    'assinafy_id' => $documentId,
                    'assinafy_status' => 'enviado',
                    'assinafy_request_log' => [
                        'document' => $responseDoc->json(),
                        'signer' => $responseSigner->json(),
                        'assignment' => $responseAssign->json(),
                    ],
                ]);

                return ['success' => true];
            }

            $errorMsg = $responseAssign->json('message') ?? $responseAssign->body();
            Log::error('Erro Assinafy (Assignment): ' . $errorMsg);

            $contrato->update([
                'assinafy_status' => 'erro_envio',
                'assinafy_request_log' => $responseAssign->json() ?? ['error' => $errorMsg],
            ]);

            return ['success' => false, 'message' => $errorMsg];

        } catch (\Exception $e) {
            Log::error('Exceção AssinafyService: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Processa o Webhook enviado pela Assinafy.
     */
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

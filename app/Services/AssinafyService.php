<?php

namespace App\Services;

use App\Models\Contrato;
use Barryvdh\DomPDF\Facade\Pdf;
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
        
        // Garante que a URL tenha o /api/v1 se for a de sandbox padrão
        if (!str_contains($this->apiUrl, '/api')) {
             $this->apiUrl .= '/api';
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
            // Endpoint: POST /accounts/{account_id}/documents
            $responseDoc = Http::withHeaders([
                'X-Api-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ])->attach(
                'file', $pdfContent, "Contrato_Matricula_{$matricula->id}.pdf"
            )->post("{$this->apiUrl}/v1/accounts/{$this->accountId}/documents");

            if (!$responseDoc->successful()) {
                throw new \Exception("Erro no Upload do Documento: " . $responseDoc->body());
            }

            $documentId = $responseDoc->json('id') ?? $responseDoc->json('data.id');
            if (!$documentId) {
                throw new \Exception("ID do documento não retornado no upload.");
            }

            // --- PASSO 2: Criar Signatário ---
            // Endpoint: POST /accounts/{account_id}/signers
            $nomeSignatario = $responsavel?->nome ?? $aluno?->nome;
            $emailSignatario = $responsavel?->email ?? $aluno?->email;

            $responseSigner = Http::withHeaders([
                'X-Api-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ])->post("{$this->apiUrl}/v1/accounts/{$this->accountId}/signers", [
                'full_name' => $nomeSignatario,
                'email' => $emailSignatario,
            ]);

            if (!$responseSigner->successful()) {
                throw new \Exception("Erro ao criar signatário: " . $responseSigner->body());
            }

            $signerId = $responseSigner->json('id') ?? $responseSigner->json('data.id');
            if (!$signerId) {
                throw new \Exception("ID do signatário não retornado.");
            }

            // --- PASSO 3: Solicitar Assinatura (Assignment) ---
            // Endpoint: POST /documents/{document_id}/assignments
            $responseAssign = Http::withHeaders([
                'X-Api-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ])->post("{$this->apiUrl}/v1/documents/{$documentId}/assignments", [
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

            $errorMsg = $responseAssign->body();
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

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

    public function __construct()
    {
        $this->apiUrl = config('services.assinafy.url', env('ASSINAFY_API_URL'));
        $this->apiKey = config('services.assinafy.key', env('ASSINAFY_API_KEY'));
    }

    /**
     * Envia um contrato para assinatura na Assinafy.
     */
    public function enviarContrato(Contrato $contrato): bool
    {
        try {
            // 1. Carregar dados relacionados para a Blade
            $contrato->load(['matriculas.pessoa', 'matriculas.turma.serie.curso', 'matriculas.periodoLetivo']);
            $matricula = $contrato->matriculas->first();

            if (! $matricula) {
                throw new \Exception("Contrato #{$contrato->id} não possui matrículas vinculadas.");
            }

            $aluno = $matricula->pessoa;
            $responsavel = $contrato->responsaveisFinanceiros->first()?->pessoa;

            // 2. Gerar PDF
            $pdf = Pdf::loadView('pdfs.contrato', [
                'contrato' => $contrato,
                'matricula' => $matricula,
                'aluno' => $aluno,
                'responsavel' => $responsavel,
                'serie' => $matricula->turma?->serie,
                'curso' => $matricula->turma?->serie?->curso,
                'periodoLetivo' => $matricula->periodoLetivo,
            ]);

            $pdfContent = base64_encode($pdf->output());

            // 3. Preparar Payload para Assinafy
            // Nota: Estrutura baseada em padrões de mercado enquanto aguardamos documentação técnica detalhada
            $payload = [
                'file' => [
                    'base64' => $pdfContent,
                    'name' => "Contrato_Matricula_{$matricula->id}.pdf",
                ],
                'signers' => [
                    [
                        'name' => $responsavel?->nome ?? $aluno?->nome,
                        'email' => $responsavel?->email ?? $aluno?->email,
                        'role' => 'signer',
                        'auth_type' => 'email', // Padrão
                    ],
                ],
                'title' => "Contrato de Prestação de Serviços - {$aluno?->nome}",
                'external_id' => (string) $contrato->id,
            ];

            // 4. Enviar para API
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Accept' => 'application/json',
            ])->post("{$this->apiUrl}/v1/documents", $payload);

            if ($response->successful()) {
                $data = $response->json();

                $contrato->update([
                    'assinafy_id' => $data['id'] ?? null,
                    'assinafy_status' => 'enviado',
                    'assinafy_request_log' => $data,
                ]);

                return true;
            }

            Log::error('Erro Assinafy (Envio): '.$response->body());

            $contrato->update([
                'assinafy_status' => 'erro_envio',
                'assinafy_request_log' => $response->json() ?? ['error' => $response->body()],
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error('Exceção AssinafyService: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Processa o Webhook enviado pela Assinafy.
     */
    public function handleWebhook(array $payload): bool
    {
        $idAssinafy = $payload['document_id'] ?? $payload['id'] ?? null;
        $status = $payload['status'] ?? null;

        if (! $idAssinafy || ! $status) {
            return false;
        }

        $contrato = Contrato::where('assinafy_id', $idAssinafy)->first();

        if ($contrato) {
            $contrato->update([
                'assinafy_status' => $status,
                'assinafy_request_log' => array_merge($contrato->assinafy_request_log ?? [], ['webhook_last' => $payload]),
            ]);

            // Lógica extra: Se assinado, baixar PDF ou ativar contrato
            if ($status === 'signed' || $status === 'completed') {
                $contrato->update(['data_aceite' => now()]);
            }

            return true;
        }

        return false;
    }
}

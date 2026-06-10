<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GeminiAgentService
{
    /**
     * Obtém e combina a base de conhecimento a partir do Manual do Usuário e do Esquema do Banco.
     * Utiliza cache de 24 horas para evitar leituras repetidas de arquivos grandes.
     */
    public function getKnowledgeBase(): string
    {
        return Cache::remember('assistant_knowledge_base', 86400, function (): string {
            $manualPath = base_path('MANUAL_USUARIO.md');
            $dbPath = base_path('GEMINI_DB.md');

            $manual = file_exists($manualPath) ? file_get_contents($manualPath) : '';
            $db = file_exists($dbPath) ? file_get_contents($dbPath) : '';

            // Limpa as quebras de linha excessivas para economizar tokens se necessário,
            // mas mantém a estrutura básica do markdown.
            return "--- INÍCIO DO MANUAL DO USUÁRIO ---\n{$manual}\n--- FIM DO MANUAL DO USUÁRIO ---\n\n".
                   "--- INÍCIO DO ESQUEMA DO BANCO DE DADOS (DB SCHEMA) ---\n{$db}\n--- FIM DO ESQUEMA DO BANCO DE DADOS (DB SCHEMA) ---";
        });
    }

    /**
     * Envia uma pergunta para o Gemini incluindo o contexto das documentações,
     * o histórico atual da sessão e a URL da página ativa.
     */
    public function ask(string $message, array $history, string $currentUrl): string
    {
        $apiKey = config('services.gemini.key');

        if (empty($apiKey)) {
            return 'Erro: A chave de API do Gemini não está configurada. Por favor, adicione a variável `GEMINI_API_KEY` no seu arquivo `.env`.';
        }

        $knowledge = $this->getKnowledgeBase();

        // Instruções de sistema para direcionar o comportamento do assistente
        $systemInstruction = "Você é o assistente virtual inteligente e oficial do sistema Torre360 (um sistema de gestão escolar desenvolvido com Laravel 12 e Filament v5).
Seu objetivo é auxiliar os usuários da plataforma com dúvidas operacionais, fluxos pedagógicos, acadêmicos, de cadastro ou financeiros.

Você DEVE responder com base UNICAMENTE nos documentos anexados abaixo (o Manual do Usuário e o Esquema de Banco de Dados do sistema):
{$knowledge}

Diretrizes obrigatórias de resposta:
1. Responda de forma concisa, educada e direta em português brasileiro.
2. Formate as suas respostas em Markdown elegante (use negritos, listas, tabelas e quebras de linha para legibilidade).
3. Se a resposta envolver guiar o usuário para alguma funcionalidade ou página do sistema, SEMPRE recomende a navegação usando links markdown normais apontando para a rota relativa correspondente no painel admin do Filament (ex: [Ir para Matrículas](/admin/matriculas), [Lançar Notas](/admin/avaliacaos), [Acessar Configurações](/admin/configuracaos), [Pessoas](/admin/pessoas)). Ao clicar, o usuário será direcionado para lá mantendo o chat aberto.
4. Você recebeu o parâmetro de URL Atual onde o usuário está navegando. Se ele fizer perguntas vagas como 'o que faço aqui?' ou 'como funciona esta tela?', utilize a URL atual para contextualizar sua explicação baseada na seção correspondente do manual.
5. Se uma dúvida não puder ser sanada pelas documentações fornecidas, diga de forma gentil que não encontrou essa informação específica no manual atual do sistema.";

        // Mapeamento das mensagens anteriores para o formato esperado pelo Gemini API (Contents payload)
        $contents = [];
        foreach ($history as $msg) {
            $contents[] = [
                'role' => $msg['role'] === 'user' ? 'user' : 'model',
                'parts' => [
                    ['text' => $msg['content']],
                ],
            ];
        }

        // Adiciona a pergunta atual com o contexto da URL
        $contents[] = [
            'role' => 'user',
            'parts' => [
                ['text' => "URL Atual: {$currentUrl}\n\nPergunta do usuário: {$message}"],
            ],
        ];

        // Endpoint da API do Gemini (usando o modelo gemini-1.5-flash estável e rápido)
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}";

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post($endpoint, [
                'contents' => $contents,
                'systemInstruction' => [
                    'parts' => [
                        ['text' => $systemInstruction],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'topP' => 0.95,
                    'maxOutputTokens' => 1500,
                ],
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Desculpe, não consegui obter uma resposta válida do assistente de IA.';
            }

            $errorMsg = $response->json('error.message') ?? $response->body();

            return "Erro ao comunicar com a API do Gemini: {$errorMsg}";

        } catch (\Exception $e) {
            return 'Ocorreu uma falha na conexão com o serviço de IA: '.$e->getMessage();
        }
    }
}

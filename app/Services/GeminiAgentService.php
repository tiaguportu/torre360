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

        // Monta o payload para o assistente
        $payload = [
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
        ];

        try {
            $data = $this->callGeminiApi($payload);

            return $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Desculpe, não consegui obter uma resposta válida do assistente de IA.';
        } catch (\Exception $e) {
            return 'Ocorreu uma falha na conexão com o serviço de IA: '.$e->getMessage();
        }
    }

    /**
     * Analisa uma mensagem bruta de texto e/ou um print de conversa (screenshot / imagem)
     * e extrai estruturadamente os dados de um Lead/Interessado usando a API do Gemini.
     *
     * @return array<string, mixed>
     */
    public function extrairLead(?string $mensagemBruta = null, ?string $imagePath = null, ?string $imageMimeType = null): array
    {
        $temTexto = ! empty(trim((string) $mensagemBruta));
        $temImagem = ! empty($imagePath);

        if (! $temTexto && ! $temImagem) {
            throw new \InvalidArgumentException('É necessário fornecer uma mensagem de texto ou um print/imagem para extrair o lead.');
        }

        $systemInstruction = 'Você é um assistente especialista em CRM comercial escolar do sistema Torre360.
Sua função é analisar mensagens de texto brutas e/ou imagens (prints/capturas de tela de conversas de WhatsApp, Instagram, e-mails, anotações ou fotos) de clientes e interessados e extrair com máxima precisão os dados cadastrais e comerciais do Lead.

Você DEVE retornar a resposta estritamente no formato JSON válido com a seguinte estrutura:
{
  "responsavel_nome": "Nome completo do responsável/interessado ou null",
  "responsavel_email": "E-mail do responsável ou null",
  "responsavel_telefone": "Telefone com DDD ou null",
  "responsavel_cpf": "CPF (apenas dígitos) ou null",
  "origem_sugerida": "Canal de origem inferido (ex: WhatsApp, Instagram, E-mail, Site, Indicação) ou null",
  "temperatura": "quente|morno|frio (quente se demonstra urgência/muito interesse, morno se busca informações gerais, frio se apenas sondagem)",
  "valor_estimado": valor_numerico_ou_null,
  "observacoes": "Resumo objetivo das necessidades e observações contidas no texto ou na imagem",
  "alunos": [
    {
      "nome": "Nome do aluno/criança ou null",
      "data_nascimento": "YYYY-MM-DD (calcule/infira se houver idade) ou null",
      "serie_pretendida": "Nome da série/ano pretendido (ex: 1º Ano, Berçário, 9º Ano) ou null",
      "vinculo": "Pai|Mãe|Tutor|Parente"
    }
  ]
}
Importante: Retorne APENAS o JSON válido sem marcações adicionais.';

        $parts = [];

        if ($temImagem) {
            if (! file_exists((string) $imagePath)) {
                throw new \Exception("Arquivo de imagem não encontrado no caminho: {$imagePath}");
            }

            $mimeType = $imageMimeType ?: (mime_content_type((string) $imagePath) ?: 'image/png');
            $imageContent = file_get_contents((string) $imagePath);

            $parts[] = [
                'inline_data' => [
                    'mime_type' => $mimeType,
                    'data' => base64_encode((string) $imageContent),
                ],
            ];
        }

        if ($temTexto) {
            $parts[] = [
                'text' => trim((string) $mensagemBruta),
            ];
        } elseif ($temImagem) {
            $parts[] = [
                'text' => 'Por favor, analise a imagem/print anexado da conversa e extraia todos os dados cadastrais e comerciais do Lead conforme as instruções.',
            ];
        }

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => $parts,
                ],
            ],
            'systemInstruction' => [
                'parts' => [
                    ['text' => $systemInstruction],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'responseMimeType' => 'application/json',
            ],
        ];

        try {
            $responseJson = $this->callGeminiApi($payload);
            $jsonText = $responseJson['candidates'][0]['content']['parts'][0]['text'] ?? '';

            // Limpa eventuais cercas markdown ```json se presentes
            $jsonClean = trim(preg_replace('/^```(?:json)?|```$/m', '', $jsonText));

            $data = json_decode($jsonClean, true);

            if (! is_array($data)) {
                throw new \Exception('A resposta da IA não pôde ser convertida em um objeto JSON válido.');
            }

            return $data;
        } catch (\Exception $e) {
            throw new \Exception('Falha ao processar dados com IA: '.$e->getMessage());
        }
    }

    /**
     * Lista de modelos do Gemini para tentar em cascata caso haja alta demanda ou sobrecarga.
     *
     * @return array<int, string>
     */
    protected function getCandidateModels(): array
    {
        $configured = config('services.gemini.model');
        $defaults = ['gemini-2.0-flash', 'gemini-1.5-flash', 'gemini-1.5-flash-8b', 'gemini-flash-latest'];

        if (! empty($configured)) {
            return array_values(array_unique(array_merge([$configured], $defaults)));
        }

        return $defaults;
    }

    /**
     * Executa a requisição à API do Gemini com fallback automático entre modelos
     * em caso de sobrecarga temporária (503 / 429 / high demand).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function callGeminiApi(array $payload): array
    {
        $apiKey = config('services.gemini.key');

        if (empty($apiKey)) {
            throw new \Exception('A chave de API do Gemini não está configurada. Adicione GEMINI_API_KEY no arquivo .env.');
        }

        $models = $this->getCandidateModels();
        $lastError = null;

        foreach ($models as $model) {
            $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

            try {
                $response = Http::timeout(45)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                    ])
                    ->post($endpoint, $payload);

                if ($response->successful()) {
                    $json = $response->json();
                    if (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
                        return $json;
                    }
                }

                $statusCode = $response->status();
                $errorMsg = (string) ($response->json('error.message') ?? $response->body());
                $lastError = $errorMsg;

                // Se for erro de demanda, rate limit ou sobrecarga temporária, tenta o próximo modelo
                $isTemporaryIssue = str_contains(strtolower($errorMsg), 'demand')
                    || str_contains(strtolower($errorMsg), 'overloaded')
                    || str_contains(strtolower($errorMsg), 'resource_exhausted')
                    || str_contains(strtolower($errorMsg), 'quota')
                    || in_array($statusCode, [429, 500, 502, 503, 504]);

                if (! $isTemporaryIssue) {
                    // Erro estrutural ou de parâmetros: lança imediatamente
                    throw new \Exception("Erro na API do Gemini: {$errorMsg}");
                }
            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                if (str_contains(strtolower($e->getMessage()), 'chave') || str_contains(strtolower($e->getMessage()), 'invalid')) {
                    throw $e;
                }
            }
        }

        throw new \Exception("Os servidores de IA do Gemini estão temporariamente com alta demanda. Por favor, tente novamente em instantes. (Detalhes: {$lastError})");
    }

    /**
     * Analisa uma mensagem bruta de texto (ex: WhatsApp, e-mail, anotação)
     * e extrai estruturadamente os dados de um Lead/Interessado usando o Gemini.
     *
     * @return array<string, mixed>
     */
    public function extrairLeadDeTexto(string $mensagemBruta): array
    {
        return $this->extrairLead(mensagemBruta: $mensagemBruta);
    }
}

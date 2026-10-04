<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DocumentoInserido;
use App\Models\HistoricoContato;
use App\Models\TipoContatoInteressado;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DocumentoIaService
{
    public function __construct(
        protected GeminiAgentService $gemini
    ) {}

    /**
     * Analisa um documento inserido com o Gemini Multimodal (OCR + Checagem de Qualidade e Conformidade).
     *
     * @return array<string, mixed>
     */
    public function analisarDocumento(DocumentoInserido $documento): array
    {
        $documento->loadMissing([
            'tipoDocumento',
            'interessado.pessoa',
            'dependente.serie',
        ]);

        $caminhoRelativo = $documento->arquivo_path;
        if (empty($caminhoRelativo) || ! Storage::disk('local')->exists($caminhoRelativo)) {
            throw new \RuntimeException('O arquivo do documento não foi encontrado no armazenamento seguro.');
        }

        $caminhoAbsoluto = Storage::disk('local')->path($caminhoRelativo);
        $mimeType = mime_content_type($caminhoAbsoluto) ?: 'application/octet-stream';
        $conteudoBase64 = base64_encode(file_get_contents($caminhoAbsoluto));

        $tipoEsperado = $documento->tipoDocumento?->nome ?? 'Documento Geral';
        $interessado = $documento->interessado;
        $dependente = $documento->dependente;

        $nomeEsperado = $dependente?->nome_crianca ?? $interessado?->pessoa?->nome ?? 'Não informado';
        $nascimentoEsperado = $dependente?->data_nascimento?->format('d/m/Y') ?? $interessado?->pessoa?->data_nascimento?->format('d/m/Y') ?? 'Não informada';
        $cpfEsperado = $interessado?->pessoa?->cpf ?? 'Não informado';

        $systemInstruction = <<<PROMPT
Você é um perito em documentoscopia e validação cadastral escolar para admissão de novos alunos do sistema Torre360.
Sua missão é inspecionar o arquivo/foto enviado pela família e avaliar:
1. Classificação do documento: Identificar que tipo de documento é (ex: certidao_nascimento, rg, cnh, comprovante_residencia, carteira_vacinacao, historico_escolar, outro).
2. Conformidade: Checar se o documento enviado confere com o que foi solicitado pela escola: "{$tipoEsperado}".
3. Qualidade da Imagem: Avaliar nitidez (se o texto está legível ou borrado), se a imagem está cortada ou se há reflexo ofuscante sobre os dados essenciais.
4. OCR & Extração de Dados: Extrair com precisão os dados cadastrais contidos no documento (nome completo, CPF, RG, data de nascimento, filiação e endereço se aplicável).
5. Cruzamento de Dados: Comparar com os dados pré-cadastrais esperados (Nome esperado: "{$nomeEsperado}", Nascimento esperado: "{$nascimentoEsperado}", CPF esperado: "{$cpfEsperado}").
6. Parecer Final e Recomendações:
   - status_sugerido: "verificado" (documento perfeito e condizente), "em_analise" (documento condizente mas com pequenas dúvidas) ou "rejeitado" (ilegível, cortado ou documento totalmente diferente do solicitado).
   - mensagem_para_familia: Se houver problemas, redigir mensagem clara, educada e empática para orientar os pais a tirarem uma foto melhor.

Retorne ESTRITAMENTE um JSON no seguinte formato (sem blocos markdown):
{
  "documento_identificado": "string (ex: certidao_nascimento, rg, cnh, comprovante_residencia, carteira_vacinacao, historico_escolar, desconhecido)",
  "confere_com_solicitado": true,
  "confianca": "alta|media|baixa",
  "qualidade": {
    "legivel": true,
    "cortado": false,
    "reflexo": false,
    "observacoes": "string explicativa da qualidade visual"
  },
  "dados_extraidos": {
    "nome_titular": "string|null",
    "cpf": "string|null (apenas números ou formatado)",
    "rg": "string|null",
    "orgao_emissor": "string|null",
    "data_nascimento": "DD/MM/AAAA|null",
    "nome_mae": "string|null",
    "nome_pai": "string|null",
    "endereco": {
      "logradouro": "string|null",
      "numero": "string|null",
      "bairro": "string|null",
      "cidade": "string|null",
      "uf": "string|null",
      "cep": "string|null"
    }
  },
  "divergencias": [
    "lista de eventuais divergências encontradas com os dados esperados"
  ],
  "status_sugerido": "verificado|em_analise|rejeitado",
  "motivo_rejeicao_sugerido": "string|null",
  "mensagem_para_familia": "string|null"
}
PROMPT;

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        [
                            'inlineData' => [
                                'mimeType' => $mimeType,
                                'data' => $conteudoBase64,
                            ],
                        ],
                        [
                            'text' => "Por favor, analise este arquivo de documento ('{$tipoEsperado}') para o candidato {$nomeEsperado} conforme as instruções do sistema.",
                        ],
                    ],
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
            $response = $this->gemini->callGeminiApi($payload);
            $textoJson = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $jsonLimpo = trim(preg_replace('/^```(?:json)?|```$/m', '', $textoJson));
            $dados = json_decode($jsonLimpo, true);

            if (! is_array($dados)) {
                throw new \RuntimeException('A resposta da IA não pôde ser decodificada em JSON.');
            }

            // Grava os metadados da análise no documento
            $documento->update([
                'dados_ia' => $dados,
                'analisado_ia_em' => now(),
            ]);

            // Registra histórico na linha do tempo do lead
            if ($interessado) {
                $docIdentificado = $dados['documento_identificado'] ?? 'Documento';
                $legivel = $dados['qualidade']['legivel'] ?? false;
                $confere = $dados['confere_com_solicitado'] ?? false;

                $relato = "✨ IA analisou '{$tipoEsperado}': identificado como {$docIdentificado}. ".
                    ($legivel ? 'Arquivo nítido e legível.' : 'Atenção: arquivo com baixa legibilidade/cortes. ').
                    ($confere ? 'Confere com o tipo solicitado.' : 'Atenção: parece diferir do solicitado.');

                $tipoContato = TipoContatoInteressado::firstOrCreate(['nome' => 'Portal de Admissão']);
                HistoricoContato::create([
                    'interessado_id' => $interessado->id,
                    'usuario_id' => $interessado->usuario_id,
                    'tipo_contato_interessado_id' => $tipoContato->id,
                    'relato' => $relato,
                    'data_contato' => now(),
                ]);
            }

            return $dados;
        } catch (Throwable $e) {
            Log::error('Erro ao validar documento com IA: '.$e->getMessage(), [
                'documento_id' => $documento->id,
                'arquivo_path' => $documento->arquivo_path,
            ]);

            throw $e;
        }
    }
}

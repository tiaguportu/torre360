<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Interessado;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

class CrmIaVendasService
{
    public function __construct(
        protected GeminiAgentService $gemini,
        protected ConsultorWhatsappService $whatsappService,
    ) {}

    /**
     * Gera um dossiê executivo e estratégico do Lead com inteligência artificial,
     * analisando perfil, dores, objeções, nível de maturidade e roteiro de abordagem.
     *
     * @return array{
     *     resumo_executivo: string,
     *     dossie_markdown: string,
     *     temperatura_sugerida: string,
     *     proxima_acao_sugerida: string,
     * }
     */
    public function gerarDossie(Interessado $interessado): array
    {
        $interessado->loadMissing([
            'pessoa',
            'dependentes.serie',
            'historicos.tipoContato',
            'historicos.usuario',
            'visitas',
            'status',
            'origem',
            'campanha',
            'usuario',
        ]);

        $contexto = $this->montarContextoLead($interessado);

        $systemInstruction = 'Você é um consultor sênior de admissões e inteligência comercial educacional do sistema Torre360 da Escola Torre de Marfim.
A escola se destaca por sua sólida formação humana e acadêmica, ambiente acolhedor, segurança, corpo docente qualificado e parceria próxima com as famílias.
Sua missão é analisar o histórico completo de um lead/família interessada e produzir um Dossiê Estratégico de Vendas em formato JSON para orientar o consultor no fechamento da matrícula.

Você DEVE retornar estritamente um JSON válido com a seguinte estrutura:
{
  "resumo_executivo": "Síntese de até 2 frases destacando quem é a família e sua principal motivação/dor.",
  "temperatura_sugerida": "quente|morno|frio",
  "proxima_acao_sugerida": "Ação imediata recomendada em 1 frase direta (ex: Convidar para o Tour Pedagógico presencial focando na turma do 3º ano).",
  "dossie_markdown": "Relatório completo e elegante formatado em Markdown com seções bem definidas:
### 👨‍👩‍👧 Perfil e Momento da Família
(Análise de quem são, valores citados e fase escolar das crianças)

### 🎯 Dores, Desejos e Objeções Identificadas
(O que mais preocupa ou atrai a família: metodologia, adaptação, valores, localização, segurança, etc.)

### 🌡️ Análise de Engajamento e Prontidão
(Justificativa da temperatura e probabilidade de matrícula com base no histórico)

### 🚀 Roteiro de Abordagem para o Consultor
- **O que falar/destacar:** (argumentos específicos para esta família)
- **Pergunta aberta estratégica:** (pergunta para conduzir a conversa)
- **Próximo passo proposto:** (agendamento, envio de proposta ou visita)"
}
Retorne APENAS o JSON puro sem cercas markdown.';

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => "Analise o seguinte lead e gere o dossiê estratégico:\n\n{$contexto}"],
                    ],
                ],
            ],
            'systemInstruction' => [
                'parts' => [
                    ['text' => $systemInstruction],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.2,
                'responseMimeType' => 'application/json',
            ],
        ];

        try {
            $response = $this->gemini->callGeminiApi($payload);
            $jsonText = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $jsonClean = trim(preg_replace('/^```(?:json)?|```$/m', '', $jsonText));
            $dados = json_decode($jsonClean, true);

            if (! is_array($dados) || empty($dados['dossie_markdown'])) {
                throw new \RuntimeException('Resposta da IA em formato inválido.');
            }

            return [
                'resumo_executivo' => (string) ($dados['resumo_executivo'] ?? 'Lead em análise pelo consultor.'),
                'temperatura_sugerida' => in_array($dados['temperatura_sugerida'] ?? '', ['quente', 'morno', 'frio'], true)
                    ? $dados['temperatura_sugerida']
                    : ($interessado->temperatura ?? 'morno'),
                'proxima_acao_sugerida' => (string) ($dados['proxima_acao_sugerida'] ?? 'Realizar contato de alinhamento com a família.'),
                'dossie_markdown' => (string) $dados['dossie_markdown'],
            ];
        } catch (Throwable $e) {
            return [
                'resumo_executivo' => 'Não foi possível gerar a síntese automatizada no momento.',
                'temperatura_sugerida' => $interessado->temperatura ?? 'morno',
                'proxima_acao_sugerida' => 'Entrar em contato para verificar o interesse da família.',
                'dossie_markdown' => "### ⚠️ Dossiê Básico (Fallback)\n\nNão foi possível processar a análise com IA neste momento: {$e->getMessage()}\n\n**Dados do Lead:**\n- **Responsável:** {$interessado->pessoa?->nome}\n- **Telefone:** {$interessado->pessoa?->telefone}\n- **Etapa atual:** {$interessado->status?->nome}",
            ];
        }
    }

    /**
     * Gera uma mensagem contextualizada e persuasiva pronta para ser enviada no WhatsApp da família.
     */
    public function gerarMensagemCopiloto(
        Interessado $interessado,
        string $objetivo,
        string $tom = 'acolhedor',
        ?string $instrucoesExtras = null,
    ): string {
        $interessado->loadMissing([
            'pessoa',
            'dependentes.serie',
            'historicos',
            'visitas',
            'status',
            'usuario',
        ]);

        $contexto = $this->montarContextoLead($interessado);

        $objetivoDescricao = match ($objetivo) {
            'primeiro_contato' => 'Primeiro contato acolhedor após o cadastro de interesse no site ou indicação, apresentando a escola e iniciando a conversa de forma amigável.',
            'convite_visita' => 'Convite caloroso para a família fazer um Tour Pedagógico presencial na escola, conhecendo a estrutura e a proposta para os filhos.',
            'quebra_objecao' => 'Superação de receios ou dúvidas levantadas pela família (como preço, adaptação escolar, rotina, segurança ou metodologia), com argumentos empáticos e seguros.',
            'reativacao' => 'Reengajamento gentil de uma família que parou de responder há alguns dias, mostrando que a escola se lembra com carinho deles e verificando se ainda buscam vaga.',
            'fechamento' => 'Incentivo ao fechamento da matrícula, destacando a reserva da vaga para a série desejada e oferecendo auxílio para o preenchimento da pré-matrícula online.',
            default => 'Contato consultivo de acompanhamento da família.',
        };

        $tomDescricao = match ($tom) {
            'objetivo' => 'Direto, profissional, conciso e prático.',
            'inspirador' => 'Entusiasta, acolhedor, vibrante e motivador.',
            default => 'Caloroso, acolhedor, empático, educado e consultivo (ideal para famílias escolares).',
        };

        $systemInstruction = "Você é o Copiloto de Atendimento e Vendas Educacionais da Escola Torre de Marfim.
Sua função é redigir uma mensagem de WhatsApp sob medida para o responsável de um aluno interessado.

Diretrizes obrigatórias da mensagem:
1. Deve ser pronta para envio pelo WhatsApp: use quebras de linha naturais, formatação sutil do WhatsApp (*negrito* em palavras-chave) e alguns emojis amigáveis (sem exagero).
2. Dirija-se ao responsável pelo primeiro nome.
3. Mencione com naturalidade o nome do(s) filho(s) e a(s) série(s) pretendida(s), se constarem nos dados.
4. O objetivo deste contato é: {$objetivoDescricao}.
5. Tom de voz desejado: {$tomDescricao}.
6. Termine SEMPRE com uma pergunta aberta e convidativa que incentive a resposta da família.
7. NÃO use marcadores de template genéricos (como [Nome]), a mensagem deve estar 100% preenchida com os dados reais.
8. Retorne APENAS o texto puro da mensagem que será copiado e colado no WhatsApp, sem aspas, sem introduções ou explicações.";

        $userPrompt = "Dados completos do lead:\n{$contexto}\n";
        if (filled($instrucoesExtras)) {
            $userPrompt .= "\nInstruções extras do consultor para este disparo: {$instrucoesExtras}\n";
        }
        $userPrompt .= "\nRedija a mensagem ideal para o WhatsApp agora:";

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $userPrompt],
                    ],
                ],
            ],
            'systemInstruction' => [
                'parts' => [
                    ['text' => $systemInstruction],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.4,
                'maxOutputTokens' => 800,
            ],
        ];

        try {
            $response = $this->gemini->callGeminiApi($payload);
            $texto = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';

            return trim(preg_replace('/^["\']|["\']$/u', '', $texto));
        } catch (Throwable $e) {
            $primeiroNome = explode(' ', trim((string) ($interessado->pessoa?->nome ?? '')))[0] ?: 'Família';
            $filho = $interessado->dependentes->first()?->nome_crianca ?? 'seu(sua) filho(a)';

            return "Olá, {$primeiroNome}! Tudo bem? Sou da equipe da Escola Torre de Marfim. Estamos muito felizes pelo seu interesse para a vaga de {$filho}. Como estão os preparativos para o próximo ano letivo? Poderíamos agendar um momento para vocês conhecerem nossa escola?";
        }
    }

    /**
     * Monta uma representação textual detalhada do lead para envio aos prompts do Gemini.
     */
    protected function montarContextoLead(Interessado $interessado): string
    {
        $linhas = [];
        $linhas[] = '=== DADOS DO INTERESSADO ===';
        $linhas[] = 'Nome do Responsável: '.($interessado->pessoa?->nome ?? 'Não informado');
        $linhas[] = 'Telefone: '.($interessado->pessoa?->telefone ?? 'Não informado');
        $linhas[] = 'E-mail: '.($interessado->pessoa?->email ?? 'Não informado');
        $linhas[] = 'Etapa no Funil: '.($interessado->status?->nome ?? 'Novo Contato');
        $linhas[] = 'Origem: '.($interessado->origem?->nome ?? 'Não identificada');
        $linhas[] = 'Temperatura Atual: '.($interessado->temperatura ?? 'morno');
        $linhas[] = 'Lead Score Atual: '.($interessado->lead_score ?? 0);
        $linhas[] = 'Valor Estimado: '.($interessado->valor_estimado ? 'R$ '.number_format((float) $interessado->valor_estimado, 2, ',', '.') : 'Não informado');
        $linhas[] = 'Distância da Escola: '.($interessado->faixa_distancia_escola ?? 'Não informada');
        $linhas[] = 'Meio de Transporte: '.($interessado->meio_transporte ?? 'Não informado');
        $linhas[] = 'Observações Gerais: '.($interessado->observacoes ?: 'Nenhuma observação cadastrada.');

        $linhas[] = "\n=== FILHOS / DEPENDENTES ===";
        if ($interessado->dependentes->isEmpty()) {
            $linhas[] = 'Nenhum dependente cadastrado ainda.';
        } else {
            foreach ($interessado->dependentes as $dep) {
                $idade = $dep->data_nascimento ? Carbon::parse($dep->data_nascimento)->age.' anos' : 'Idade não informada';
                $serie = $dep->serie?->nome ?? $dep->serie_pretendida ?? 'Série não informada';
                $linhas[] = "- {$dep->nome_crianca} ({$idade}) — Série pretendida: {$serie}";
            }
        }

        $linhas[] = "\n=== HISTÓRICO DE CONTATOS (Cronológico) ===";
        if ($interessado->historicos->isEmpty()) {
            $linhas[] = 'Nenhum contato anterior registrado.';
        } else {
            $historicosOrdenados = $interessado->historicos->sortBy('data_contato');
            foreach ($historicosOrdenados as $h) {
                $data = $h->data_contato ? Carbon::parse($h->data_contato)->format('d/m/Y H:i') : 'Data não registrada';
                $tipo = $h->tipoContato?->nome ?? 'Contato';
                $atendente = $h->usuario?->name ?? 'Sistema';
                $linhas[] = "[{$data}] {$tipo} por {$atendente}: {$h->relato}";
            }
        }

        $linhas[] = "\n=== VISITAS À ESCOLA ===";
        if ($interessado->visitas->isEmpty()) {
            $linhas[] = 'Nenhuma visita registrada ou agendada.';
        } else {
            foreach ($interessado->visitas as $v) {
                $dataVisita = $v->data_hora ? Carbon::parse($v->data_hora)->format('d/m/Y H:i') : 'Data não informada';
                $statusVisita = $v->status ?? 'agendada';
                $linhas[] = "- Visita em {$dataVisita} (Status: {$statusVisita}) — Obs: ".($v->observacoes ?: 'Sem observações adicionais.');
            }
        }

        return implode("\n", $linhas);
    }

    /**
     * Remove emojis e glifos gráficos não suportados pelas fontes de renderização do DomPDF,
     * evitando que apareçam como "?????" no documento gerado.
     */
    public function sanitizarTextoParaPdf(?string $texto): string
    {
        if (blank($texto)) {
            return '';
        }

        // 1. Remove emojis e sequências Unicode estendidas (incluindo seletores de variação FE0E/FE0F e conectores ZWJ)
        $limpo = preg_replace('/(?:\x{FE0E}|\x{FE0F}|\x{200D}|\p{Extended_Pictographic})+/u', '', $texto);

        // 2. Remove espaços extras deixados após a remoção de emojis em títulos Markdown (ex: "###  Dores" -> "### Dores")
        $limpo = preg_replace('/^(#{1,6})[ \t]+/m', '$1 ', (string) $limpo);

        // 3. Normaliza espaços múltiplos
        $limpo = preg_replace('/[ \t]{2,}/', ' ', (string) $limpo);

        return trim((string) $limpo);
    }

    /**
     * Gera o documento PDF do Dossiê Estratégico IA pronto para visualização ou download.
     *
     * @param array{
     *     resumo_executivo?: ?string,
     *     dossie_markdown?: ?string,
     *     temperatura_sugerida?: ?string,
     *     proxima_acao_sugerida?: ?string,
     * }|null $dadosDossie
     */
    public function gerarPdfDossie(Interessado $interessado, ?array $dadosDossie = null): DomPdf
    {
        $interessado->loadMissing([
            'pessoa',
            'dependentes.serie',
            'status',
            'origem',
            'campanha',
            'usuario',
        ]);

        if (empty($dadosDossie['dossie_markdown'])) {
            $cached = cache()->get("dossie_ia_lead_{$interessado->id}");
            if (is_array($cached) && ! empty($cached['dossie_markdown'])) {
                $dadosDossie = $cached;
            } else {
                $dadosDossie = $this->gerarDossie($interessado);
            }
        }

        $resumoLimpo = $this->sanitizarTextoParaPdf($dadosDossie['resumo_executivo'] ?? '');
        $proximaAcaoLimpa = $this->sanitizarTextoParaPdf($dadosDossie['proxima_acao_sugerida'] ?? '');
        $markdownLimpo = $this->sanitizarTextoParaPdf($dadosDossie['dossie_markdown'] ?? 'Dossiê não disponível.');

        $dadosDossieLimpo = array_merge($dadosDossie, [
            'resumo_executivo' => $resumoLimpo,
            'proxima_acao_sugerida' => $proximaAcaoLimpa,
            'dossie_markdown' => $markdownLimpo,
        ]);

        $dossieHtml = Str::markdown($markdownLimpo);

        return Pdf::loadView('pdfs.dossie-estrategico', [
            'interessado' => $interessado,
            'dossie' => $dadosDossieLimpo,
            'dossieHtml' => $dossieHtml,
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('defaultFont', 'DejaVu Sans');
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NivelBloom;
use App\Models\User;
use App\Services\CopilotoPedagogico\Exceptions\CopilotoIndisponivelException;
use App\Services\CopilotoPedagogico\Exceptions\LimiteDeUsoException;
use App\Services\CopilotoPedagogico\Exceptions\PeiDesabilitadoException;
use App\Services\CopilotoPedagogico\Exceptions\RespostaIaInvalidaException;
use App\Services\CopilotoPedagogico\GerarPeiInput;
use App\Services\CopilotoPedagogico\GerarPlanoAulaInput;
use App\Services\CopilotoPedagogico\PeiGeradoOutput;
use App\Services\CopilotoPedagogico\PlanoAulaGeradoOutput;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class CopilotoPedagogicoIaService
{
    public function __construct(
        protected GeminiAgentService $geminiService,
    ) {}

    /**
     * Gera uma proposta estruturada de plano de aula alinhada à BNCC e taxonomia de Bloom.
     *
     * @throws CopilotoIndisponivelException
     * @throws RespostaIaInvalidaException
     * @throws LimiteDeUsoException
     */
    public function gerarPlanoAula(GerarPlanoAulaInput $input, User $user): PlanoAulaGeradoOutput
    {
        $this->verificarRateLimit($user);

        $timeout = (int) config('copiloto_pedagogico.timeout', 90);
        $temperatura = (float) config('copiloto_pedagogico.temperatura', 0.3);

        // Prepara lista segura de habilidades com código, nome e descrição
        $habilidadesTexto = [];
        $codigosPermitidos = [];
        $idsPorCodigo = [];

        foreach ($input->habilidades as $hab) {
            $codigosPermitidos[] = $hab['codigo'];
            $idsPorCodigo[$hab['codigo']] = $hab['id'];
            $habilidadesTexto[] = "- Código [{$hab['codigo']}]: {$hab['nome']}. Descrição oficial BNCC: {$hab['descricao']}";
        }

        $habilidadesFormatadas = implode("\n", $habilidadesTexto);
        $duracaoTotal = $input->duracaoTotalMinutos();

        $instrucoesExtrasSanitizadas = '';
        if (filled($input->instrucoesExtras)) {
            $higienizado = CrmIaVendasService::ocultarDadosPessoais($input->instrucoesExtras);
            $delimitado = GeminiAgentService::delimitarEntradaSegura($higienizado, 'orientacoes_docente');
            $instrucoesExtrasSanitizadas = "\nOrientações pedagógicas específicas fornecidas pelo docente:\n{$delimitado}\n";
        }

        $systemInstruction = config('copiloto_pedagogico.persona_plano_aula');

        $prompt = <<<PROMPT
Você deve planejar uma sequência didática de {$input->numeroAulas} aula(s) com duração total de {$duracaoTotal} minutos ({$input->duracaoMinutosPorAula} min por aula).
Turma: {$input->turmaNome} (Série/Ano: {$input->serieNome}).
Componente Curricular / Disciplina: {$input->disciplinaNome}.

HABILIDADES OFICIAIS DA BNCC SELECIONADAS:
{$habilidadesFormatadas}
{$instrucoesExtrasSanitizadas}
DIRETRIZES OBRIGATÓRIAS:
1. Todos os objetivos de aprendizagem devem estar associados estritamente aos códigos de habilidade fornecidos acima. NÃO invente novos códigos.
2. Cada objetivo deve indicar o nível cognitivo conforme a Taxonomia de Bloom Revisada (Lembrar, Compreender, Aplicar, Analisar, Avaliar, Criar).
3. O cronograma deve conter as etapas didáticas (Aquecimento/Introdução, Desenvolvimento, Fechamento/Sistematização) com tempos em minutos cuja soma totalize EXATAMENTE {$duracaoTotal} minutos.
4. Para cada etapa, descreva claramente o papel mediador do professor e o papel ativo dos estudantes.
5. Proponha uma Metodologia Ativa de Aprendizagem pertinente com justificativa pedagógica.
6. A avaliação deve ser formativa, contendo instrumento e critérios claros.

Retorne EXCLUSIVAMENTE um objeto JSON válido (sem marcadores markdown adicionais) com a seguinte estrutura:
{
  "objetivos": [
    {
      "descricao": "Texto claro do objetivo",
      "habilidade_codigo": "Código exato recebido (ex: EF06MA01)",
      "nivel_bloom": "Lembrar|Compreender|Aplicar|Analisar|Avaliar|Criar"
    }
  ],
  "metodologia_ativa": {
    "nome": "Nome da metodologia ativa (ex: Sala de Aula Invertida, Rotação por Estações, Aprendizagem Baseada em Problemas)",
    "justificativa": "Por que esta metodologia favorece o alcance dos objetivos"
  },
  "recursos": [
    "Recurso didático 1",
    "Recurso didático 2"
  ],
  "cronograma": [
    {
      "etapa": "Nome da etapa (ex: Aquecimento e Sondagem Prévia)",
      "minutos": 10,
      "atividade": "Descrição da atividade prática",
      "papel_professor": "Ação mediadora do docente",
      "papel_aluno": "Ação engajada dos estudantes"
    }
  ],
  "avaliacao_formativa": [
    {
      "instrumento": "Instrumento avaliativo (ex: Rubrica observacional, Ticket de saída)",
      "criterio": "Critério de sucesso da aprendizagem",
      "nivel_bloom": "Lembrar|Compreender|Aplicar|Analisar|Avaliar|Criar"
    }
  ],
  "observacoes": "Recomendações adicionais ou null"
}
PROMPT;

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'systemInstruction' => [
                'parts' => [
                    ['text' => $systemInstruction],
                ],
            ],
            'generationConfig' => [
                'temperature' => $temperatura,
                'responseMimeType' => 'application/json',
            ],
        ];

        $tentativa = 1;
        $dadosJson = null;

        while ($tentativa <= 2) {
            try {
                $resposta = $this->geminiService->callGeminiApi($payload, $timeout);
                $textoBruto = $resposta['candidates'][0]['content']['parts'][0]['text'] ?? '';
                $textoLimpo = trim(preg_replace('/^```(?:json)?|```$/m', '', $textoBruto));
                $dados = json_decode($textoLimpo, true);

                if (is_array($dados) && ! empty($dados['objetivos']) && ! empty($dados['cronograma'])) {
                    $dadosJson = $dados;
                    break;
                }
            } catch (Throwable $e) {
                if ($tentativa === 2) {
                    throw new CopilotoIndisponivelException('Falha na comunicação com o assistente inteligente: '.GeminiAgentService::sanitizarMensagemDeErro($e->getMessage()));
                }
            }

            // Tentativa corretiva com reforço estrutural
            $payload['contents'][] = [
                'role' => 'model',
                'parts' => [['text' => '{"erro": "invalido"}']],
            ];
            $payload['contents'][] = [
                'role' => 'user',
                'parts' => [['text' => 'A resposta anterior não atendeu à estrutura JSON solicitada. Retorne estritamente o JSON com as chaves objetivos, metodologia_ativa, recursos, cronograma e avaliacao_formativa.']],
            ];
            $tentativa++;
        }

        if (! is_array($dadosJson)) {
            throw new RespostaIaInvalidaException;
        }

        return $this->processarEValidarPlanoAula($dadosJson, $codigosPermitidos, $idsPorCodigo, $duracaoTotal);
    }

    /**
     * Gera proposta confidencial de adaptação curricular / PEI para um estudante com necessidades especiais.
     *
     * @throws PeiDesabilitadoException
     * @throws CopilotoIndisponivelException
     * @throws RespostaIaInvalidaException
     * @throws LimiteDeUsoException
     */
    public function gerarAdaptacaoPei(GerarPeiInput $input, User $user): PeiGeradoOutput
    {
        // Trava de segurança LGPD obrigatória
        if (! config('copiloto_pedagogico.pei_habilitado', false)) {
            throw new PeiDesabilitadoException;
        }

        $this->verificarRateLimit($user);

        $timeout = (int) config('copiloto_pedagogico.timeout', 90);
        $temperatura = (float) config('copiloto_pedagogico.temperatura', 0.3);

        // Prepara conjunto estritamente minimizado (LGPD - Arts. 6º III, 11 e 14)
        $necessidades = ! empty($input->categoriasNecessidades)
            ? implode(', ', $input->categoriasNecessidades)
            : 'Nenhuma necessidade especial específica registrada';

        $transtornos = ! empty($input->categoriasTranstornos)
            ? implode(', ', $input->categoriasTranstornos)
            : 'Nenhum transtorno funcional de aprendizagem registrado';

        $recursos = ! empty($input->categoriasRecursos)
            ? implode(', ', $input->categoriasRecursos)
            : 'Recursos pedagógicos regulares da sala';

        $habilidadesListadas = array_map(
            fn ($h) => "[{$h['codigo']}] {$h['nome']}",
            $input->habilidadesBncc
        );
        $habilidadesTexto = implode('; ', $habilidadesListadas);

        $obsHigienizada = '';
        if (filled($input->observacaoPedagogicaHigienizada)) {
            $limpo = CrmIaVendasService::ocultarDadosPessoais($input->observacaoPedagogicaHigienizada);
            $delimitado = GeminiAgentService::delimitarEntradaSegura($limpo, 'observacoes_pedagogicas_anonimizadas');
            $obsHigienizada = "\nObservações pedagógicas adicionais sobre a dinâmica de aprendizagem:\n{$delimitado}\n";
        }

        $systemInstruction = config('copiloto_pedagogico.persona_pei');

        $prompt = <<<PROMPT
Você deve elaborar uma proposta de adaptação pedagógica e curricular inclusiva (PEI) para a MESMA atividade planejada para a turma.

DADOS PEDAGÓGICOS MINIMIZADOS DO ESTUDANTE:
- Nível/Etapa de Ensino: {$input->etapaEnsino}
- Faixa Etária Aproximada: {$input->faixaEtariaAnos} anos
- Necessidades Educacionais Especiais cadastradas: {$necessidades}
- Transtornos de Aprendizagem informados: {$transtornos}
- Recursos de Acessibilidade / Tecnologia Assistiva recomendados: {$recursos}
{$obsHigienizada}

ATIVIDADE DA TURMA A SER ADAPTADA:
- Componente Curricular: {$input->disciplinaNome}
- Tema e Objetivo Central da Aula: {$input->temaOuObjetivoAula}
- Habilidade(s) Curricular(es) Alvo da BNCC: {$habilidadesTexto}

DIRETRIZES DE INCLUSÃO (LEI Nº 13.146/2015, DUA E PNEEPEI):
1. O estudante deve participar da mesma proposta didática dos colegas, sem exclusão ou isolamento.
2. Mantenha o objetivo curricular e a habilidade alvo da BNCC. Adapte o formato de instrução, os materiais de apoio e o tempo. Caso a flexibilização exija ajuste de profundidade, indique explicitamente que requer validação do AEE.
3. Proponha adaptações práticas compatíveis com os recursos de acessibilidade citados (apoio visual, quebra em etapas curtas, recursos multissensoriais, ampliação de tempo com justificativa).
4. Forneça estratégias mediadoras diretas para o docente regente em sala de aula comum.
5. Proponha formato acessível de avaliação da aprendizagem compatível com o perfil.
6. AVISO ESTATUTÁRIO: NUNCA prescreva laudos, remédios ou diagnósticos clínicos.

Retorne EXCLUSIVAMENTE um objeto JSON válido estruturado como:
{
  "barreiras": "Descrição objetiva das principais barreiras pedagógicas de acesso à atividade",
  "adaptacoes_praticas": {
    "instrucoes_passo_a_passo": "Orientações simplificadas e fragmentadas em etapas claras",
    "apoio_visual": "Recursos visuais, esquemas, cartões ou sinalizações sugeridas",
    "tempo_estendido": "Sugestão de tempo extra estimado e respectiva justificativa pedagógica",
    "recursos_acessibilidade": "Uso específico de tecnologia assistiva ou materiais adaptados",
    "organizacao_ambiente": "Organização espacial, iluminação ou posicionamento físico na sala"
  },
  "adaptacao_avaliacao": "Como avaliar a mesma habilidade por meio de formatos acessíveis",
  "estrategias_professor": "Ações e perguntas mediadoras que o professor pode aplicar durante a aula",
  "indicadores_acompanhamento": "Critérios observáveis de engajamento e evolução na tarefa",
  "prazo_revisao_dias": 15,
  "habilidade_bncc_alvo": "Confirmação da manutenção da habilidade BNCC alvo",
  "profundidade_requer_validacao_aee": null
}
PROMPT;

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'systemInstruction' => [
                'parts' => [
                    ['text' => $systemInstruction],
                ],
            ],
            'generationConfig' => [
                'temperature' => $temperatura,
                'responseMimeType' => 'application/json',
            ],
        ];

        try {
            $resposta = $this->geminiService->callGeminiApi($payload, $timeout);
            $textoBruto = $resposta['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $textoLimpo = trim(preg_replace('/^```(?:json)?|```$/m', '', $textoBruto));
            $dados = json_decode($textoLimpo, true);

            if (! is_array($dados) || empty($dados['barreiras']) || empty($dados['adaptacoes_praticas'])) {
                throw new RespostaIaInvalidaException;
            }

            return new PeiGeradoOutput(
                barreiras: (string) $dados['barreiras'],
                adaptacoesPraticas: [
                    'instrucoes_passo_a_passo' => (string) ($dados['adaptacoes_praticas']['instrucoes_passo_a_passo'] ?? ''),
                    'apoio_visual' => (string) ($dados['adaptacoes_praticas']['apoio_visual'] ?? ''),
                    'tempo_estendido' => (string) ($dados['adaptacoes_praticas']['tempo_estendido'] ?? ''),
                    'recursos_acessibilidade' => (string) ($dados['adaptacoes_praticas']['recursos_acessibilidade'] ?? ''),
                    'organizacao_ambiente' => (string) ($dados['adaptacoes_praticas']['organizacao_ambiente'] ?? ''),
                ],
                adaptacaoAvaliacao: (string) ($dados['adaptacao_avaliacao'] ?? ''),
                estrategiasProfessor: (string) ($dados['estrategias_professor'] ?? ''),
                indicadoresAcompanhamento: (string) ($dados['indicadores_acompanhamento'] ?? ''),
                prazoRevisaoDias: (int) ($dados['prazo_revisao_dias'] ?? 15),
                habilidadeBnccAlvo: (string) ($dados['habilidade_bncc_alvo'] ?? $habilidadesTexto),
                profundidadeRequerValidacaoAee: filled($dados['profundidade_requer_validacao_aee'] ?? null)
                    ? (string) $dados['profundidade_requer_validacao_aee']
                    : null,
            );
        } catch (Throwable $e) {
            if ($e instanceof RespostaIaInvalidaException || $e instanceof PeiDesabilitadoException || $e instanceof LimiteDeUsoException) {
                throw $e;
            }

            throw new CopilotoIndisponivelException('Erro na geração da adaptação PEI: '.GeminiAgentService::sanitizarMensagemDeErro($e->getMessage()));
        }
    }

    /**
     * Valida e ajusta a coerência curricular da resposta JSON recebida da IA.
     *
     * @param  array<string, mixed>  $dados
     * @param  list<string>  $codigosPermitidos
     * @param  array<string, int>  $idsPorCodigo
     */
    protected function processarEValidarPlanoAula(
        array $dados,
        array $codigosPermitidos,
        array $idsPorCodigo,
        int $duracaoTotalEsperadaMinutos,
    ): PlanoAulaGeradoOutput {
        $avisos = [];

        // 1. Validação e saneamento dos objetivos em relação aos códigos BNCC
        $objetivosSaneados = [];
        $habilidadeIdsUtilizados = [];

        foreach ($dados['objetivos'] ?? [] as $obj) {
            $codigo = trim((string) ($obj['habilidade_codigo'] ?? ''));
            if (! in_array($codigo, $codigosPermitidos, true)) {
                $avisos[] = "O código BNCC '{$codigo}' sugerido pela IA não constava nas habilidades selecionadas e foi descartado.";

                continue;
            }

            $nivelBloomStr = trim((string) ($obj['nivel_bloom'] ?? 'Compreender'));
            $nivelBloom = NivelBloom::tryFrom($nivelBloomStr)?->value ?? NivelBloom::Compreender->value;

            $objetivosSaneados[] = [
                'descricao' => (string) ($obj['descricao'] ?? ''),
                'habilidade_codigo' => $codigo,
                'nivel_bloom' => $nivelBloom,
            ];

            if (isset($idsPorCodigo[$codigo])) {
                $habilidadeIdsUtilizados[$idsPorCodigo[$codigo]] = true;
            }
        }

        if (empty($objetivosSaneados)) {
            // Fallback resiliente: vincula os objetivos ao primeiro código permitido
            $primeiroCodigo = $codigosPermitidos[0] ?? 'BNCC';
            foreach ($dados['objetivos'] ?? [] as $obj) {
                $objetivosSaneados[] = [
                    'descricao' => (string) ($obj['descricao'] ?? ''),
                    'habilidade_codigo' => $primeiroCodigo,
                    'nivel_bloom' => NivelBloom::Compreender->value,
                ];
            }
            if (isset($idsPorCodigo[$primeiroCodigo])) {
                $habilidadeIdsUtilizados[$idsPorCodigo[$primeiroCodigo]] = true;
            }
        }

        // 2. Validação da soma de minutos do cronograma didático
        $cronograma = [];
        $somaMinutos = 0;

        foreach ($dados['cronograma'] ?? [] as $etapa) {
            $minutos = max(5, (int) ($etapa['minutos'] ?? 10));
            $somaMinutos += $minutos;

            $cronograma[] = [
                'etapa' => (string) ($etapa['etapa'] ?? 'Desenvolvimento'),
                'minutos' => $minutos,
                'atividade' => (string) ($etapa['atividade'] ?? ''),
                'papel_professor' => (string) ($etapa['papel_professor'] ?? ''),
                'papel_aluno' => (string) ($etapa['papel_aluno'] ?? ''),
            ];
        }

        // Se houver discrepância maior que 5%, compensa suavemente na última etapa do cronograma
        $diferencaMinutos = $duracaoTotalEsperadaMinutos - $somaMinutos;
        if (abs($diferencaMinutos) > ($duracaoTotalEsperadaMinutos * 0.05)) {
            $avisos[] = "A soma dos tempos das atividades ({$somaMinutos} min) divergiu da duração planejada ({$duracaoTotalEsperadaMinutos} min) e foi ajustada proporcionalmente.";

            $ultimaIndex = count($cronograma) - 1;
            if ($ultimaIndex >= 0) {
                $cronograma[$ultimaIndex]['minutos'] = max(5, $cronograma[$ultimaIndex]['minutos'] + $diferencaMinutos);
                $somaMinutos = $duracaoTotalEsperadaMinutos;
            }
        }

        // 3. Avaliação formativa
        $avaliacaoFormativa = [];
        foreach ($dados['avaliacao_formativa'] ?? [] as $av) {
            $nivelBloomStr = trim((string) ($av['nivel_bloom'] ?? 'Compreender'));
            $nivelBloom = NivelBloom::tryFrom($nivelBloomStr)?->value ?? NivelBloom::Compreender->value;

            $avaliacaoFormativa[] = [
                'instrumento' => (string) ($av['instrumento'] ?? 'Observação direta'),
                'criterio' => (string) ($av['criterio'] ?? 'Participação e execução'),
                'nivel_bloom' => $nivelBloom,
            ];
        }

        $metodologia = [
            'nome' => (string) ($dados['metodologia_ativa']['nome'] ?? 'Metodologia Ativa de Aprendizagem'),
            'justificativa' => (string) ($dados['metodologia_ativa']['justificativa'] ?? 'Engajamento prático e contextualizado'),
        ];

        $recursos = array_map(fn ($r) => (string) $r, (array) ($dados['recursos'] ?? []));

        return new PlanoAulaGeradoOutput(
            objetivos: $objetivosSaneados,
            metodologiaAtiva: $metodologia,
            recursos: $recursos,
            cronograma: $cronograma,
            avaliacaoFormativa: $avaliacaoFormativa,
            observacoes: filled($dados['observacoes'] ?? null) ? (string) $dados['observacoes'] : null,
            habilidadeIds: array_keys($habilidadeIdsUtilizados),
            duracaoTotalPlanejadaMinutos: $somaMinutos,
            avisos: $avisos,
        );
    }

    /**
     * Aplica controle de taxa de requisições por usuário por minuto.
     *
     * @throws LimiteDeUsoException
     */
    protected function verificarRateLimit(User $user): void
    {
        $limitePorMinuto = (int) config('copiloto_pedagogico.limite_por_minuto', 10);
        $chave = 'copiloto_pedagogico_user_'.$user->id;

        if (RateLimiter::tooManyAttempts($chave, $limitePorMinuto)) {
            throw new LimiteDeUsoException;
        }

        RateLimiter::hit($chave, 60);
    }

    /**
     * Regenera uma seção específica do plano de aula mantendo alinhamento às habilidades e diretrizes.
     *
     * @param  'objetivos'|'metodologia'|'recursos'|'avaliacao'  $secao
     *
     * @throws CopilotoIndisponivelException
     * @throws RespostaIaInvalidaException
     * @throws LimiteDeUsoException
     */
    public function regenerarSecaoPlanoAula(
        GerarPlanoAulaInput $input,
        string $secao,
        ?string $instrucaoCurta,
        User $user
    ): string {
        $this->verificarRateLimit($user);

        $timeout = (int) config('copiloto_pedagogico.timeout', 90);
        $temperatura = (float) config('copiloto_pedagogico.temperatura', 0.4);

        $habilidadesTexto = [];
        foreach ($input->habilidades as $hab) {
            $habilidadesTexto[] = "- Código [{$hab['codigo']}]: {$hab['nome']}";
        }
        $habilidadesFormatadas = implode("\n", $habilidadesTexto);
        $duracaoTotal = $input->duracaoTotalMinutos();

        $ajusteDocente = '';
        if (filled($instrucaoCurta)) {
            $limpo = CrmIaVendasService::ocultarDadosPessoais($instrucaoCurta);
            $delimitado = GeminiAgentService::delimitarEntradaSegura($limpo, 'instrucao_ajuste');
            $ajusteDocente = "\nInstrução específica do docente para esta regeneração:\n{$delimitado}\n";
        }

        $systemInstruction = config('copiloto_pedagogico.persona_plano_aula');

        [$prompt, $chaveValidacao] = match ($secao) {
            'objetivos' => [
                <<<PROMPT
Você deve regenerar EXCLUSIVAMENTE a seção de OBJETIVOS DE APRENDIZAGEM para um plano de aula de {$input->numeroAulas} aula(s) ({$duracaoTotal} minutos no total).
Turma: {$input->turmaNome} ({$input->serieNome}).
Disciplina: {$input->disciplinaNome}.
Habilidades BNCC selecionadas:
{$habilidadesFormatadas}
{$ajusteDocente}
Diretriz: Retorne EXCLUSIVAMENTE um objeto JSON no formato:
{
  "objetivos": [
    {
      "descricao": "Texto claro do objetivo",
      "habilidade_codigo": "Código exato recebido",
      "nivel_bloom": "Lembrar|Compreender|Aplicar|Analisar|Avaliar|Criar"
    }
  ]
}
PROMPT,
                'objetivos',
            ],
            'metodologia' => [
                <<<PROMPT
Você deve regenerar EXCLUSIVAMENTE a seção de METODOLOGIA ATIVA E CRONOGRAMA DE ETAPAS para um plano de aula de {$input->numeroAulas} aula(s) com duração total de EXATAMENTE {$duracaoTotal} minutos.
Turma: {$input->turmaNome} ({$input->serieNome}).
Disciplina: {$input->disciplinaNome}.
Habilidades BNCC selecionadas:
{$habilidadesFormatadas}
{$ajusteDocente}
Diretriz: Retorne EXCLUSIVAMENTE um objeto JSON no formato:
{
  "metodologia_ativa": {
    "nome": "Nome da metodologia ativa",
    "justificativa": "Justificativa pedagógica"
  },
  "cronograma": [
    {
      "etapa": "Nome da etapa",
      "minutos": 10,
      "atividade": "Descrição da atividade prática",
      "papel_professor": "Mediação docente",
      "papel_aluno": "Atividade do aluno"
    }
  ]
}
PROMPT,
                'cronograma',
            ],
            'recursos' => [
                <<<PROMPT
Você deve regenerar EXCLUSIVAMENTE a lista de RECURSOS DIDÁTICOS E MATERIAIS para um plano de aula de {$input->numeroAulas} aula(s).
Turma: {$input->turmaNome} ({$input->serieNome}).
Disciplina: {$input->disciplinaNome}.
Habilidades BNCC selecionadas:
{$habilidadesFormatadas}
{$ajusteDocente}
Diretriz: Retorne EXCLUSIVAMENTE um objeto JSON no formato:
{
  "recursos": [
    "Recurso 1",
    "Recurso 2"
  ]
}
PROMPT,
                'recursos',
            ],
            'avaliacao' => [
                <<<PROMPT
Você deve regenerar EXCLUSIVAMENTE a seção de AVALIAÇÃO FORMATIVA para o plano de aula.
Turma: {$input->turmaNome} ({$input->serieNome}).
Disciplina: {$input->disciplinaNome}.
Habilidades BNCC selecionadas:
{$habilidadesFormatadas}
{$ajusteDocente}
Diretriz: Retorne EXCLUSIVAMENTE um objeto JSON no formato:
{
  "avaliacao_formativa": [
    {
      "instrumento": "Instrumento avaliativo",
      "criterio": "Critério de avaliação",
      "nivel_bloom": "Lembrar|Compreender|Aplicar|Analisar|Avaliar|Criar"
    }
  ],
  "observacoes": "Observações avaliativas ou null"
}
PROMPT,
                'avaliacao_formativa',
            ],
            default => throw new \InvalidArgumentException("Seção inválida: {$secao}")
        };

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'systemInstruction' => [
                'parts' => [
                    ['text' => $systemInstruction],
                ],
            ],
            'generationConfig' => [
                'temperature' => $temperatura,
                'responseMimeType' => 'application/json',
            ],
        ];

        try {
            $resposta = $this->geminiService->callGeminiApi($payload, $timeout);
            $textoBruto = $resposta['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $textoLimpo = trim(preg_replace('/^```(?:json)?|```$/m', '', $textoBruto));
            $dados = json_decode($textoLimpo, true);

            if (! is_array($dados) || empty($dados[$chaveValidacao])) {
                throw new RespostaIaInvalidaException;
            }

            return match ($secao) {
                'objetivos' => $this->formatarObjetivosTexto((array) $dados['objetivos']),
                'metodologia' => $this->formatarMetodologiaTexto((array) ($dados['metodologia_ativa'] ?? []), (array) ($dados['cronograma'] ?? [])),
                'recursos' => $this->formatarRecursosTexto((array) $dados['recursos']),
                'avaliacao' => $this->formatarAvaliacaoTexto((array) $dados['avaliacao_formativa'], isset($dados['observacoes']) ? (string) $dados['observacoes'] : null),
            };
        } catch (Throwable $e) {
            if ($e instanceof RespostaIaInvalidaException || $e instanceof LimiteDeUsoException) {
                throw $e;
            }

            throw new CopilotoIndisponivelException('Falha na comunicação com o assistente inteligente: '.GeminiAgentService::sanitizarMensagemDeErro($e->getMessage()));
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $objetivos
     */
    protected function formatarObjetivosTexto(array $objetivos): string
    {
        $linhas = [];
        foreach ($objetivos as $i => $obj) {
            $num = $i + 1;
            $codigo = $obj['habilidade_codigo'] ?? 'BNCC';
            $bloom = $obj['nivel_bloom'] ?? 'Compreender';
            $descricao = $obj['descricao'] ?? '';
            $linhas[] = "{$num}. [{$codigo}] ({$bloom}): {$descricao}";
        }

        return implode("\n", $linhas);
    }

    /**
     * @param  array<string, mixed>  $metodologiaAtiva
     * @param  array<int, array<string, mixed>>  $cronograma
     */
    protected function formatarMetodologiaTexto(array $metodologiaAtiva, array $cronograma): string
    {
        $linhas = [];
        if (! empty($metodologiaAtiva['nome'])) {
            $linhas[] = "METODOLOGIA ATIVA: {$metodologiaAtiva['nome']}";
            if (! empty($metodologiaAtiva['justificativa'])) {
                $linhas[] = "Justificativa: {$metodologiaAtiva['justificativa']}\n";
            }
        }

        $linhas[] = 'CRONOGRAMA DETALHADO DA AULA:';
        foreach ($cronograma as $etapa) {
            $nomeEtapa = $etapa['etapa'] ?? 'Etapa';
            $minutos = $etapa['minutos'] ?? 10;
            $atividade = $etapa['atividade'] ?? '';
            $papelProf = $etapa['papel_professor'] ?? '-';
            $papelAluno = $etapa['papel_aluno'] ?? '-';

            $linhas[] = "• {$nomeEtapa} ({$minutos} min): {$atividade}";
            $linhas[] = "  - Professor: {$papelProf}";
            $linhas[] = "  - Estudantes: {$papelAluno}";
        }

        return implode("\n", $linhas);
    }

    /**
     * @param  array<int, mixed>  $recursos
     */
    protected function formatarRecursosTexto(array $recursos): string
    {
        return implode("\n", array_map(fn ($r): string => "• {$r}", $recursos));
    }

    /**
     * @param  array<int, array<string, mixed>>  $avaliacaoFormativa
     */
    protected function formatarAvaliacaoTexto(array $avaliacaoFormativa, ?string $observacoes = null): string
    {
        $linhas = [];
        $linhas[] = 'AVALIAÇÃO FORMATIVA:';
        foreach ($avaliacaoFormativa as $av) {
            $instrumento = $av['instrumento'] ?? 'Instrumento avaliativo';
            $nivelBloom = $av['nivel_bloom'] ?? 'Avaliar';
            $criterio = $av['criterio'] ?? '-';

            $linhas[] = "• Instrumento: {$instrumento} (Nível: {$nivelBloom})";
            $linhas[] = "  Critério: {$criterio}";
        }

        if (! empty($observacoes)) {
            $linhas[] = "\nObservações: {$observacoes}";
        }

        return implode("\n", $linhas);
    }
}

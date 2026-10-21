<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Ativação do Copiloto de Inclusão e PEI
    |--------------------------------------------------------------------------
    |
    | Em conformidade com a LGPD (Arts. 11, 14, 33-36 e 46) e as diretrizes
    | institucionais (pendências M9 e A3), o envio de dados pedagógicos de
    | estudantes com necessidades especiais para modelos de IA exige plano
    | empresarial com garantia de privacidade e contrato específico de não
    | utilização dos dados para treinamento.
    |
    | Mantido OBRIGATORIAMENTE em FALSE por padrão até validação jurídica e técnica.
    |
    */
    'pei_habilitado' => (bool) env('COPILOTO_PEDAGOGICO_PEI_HABILITADO', false),

    /*
    |--------------------------------------------------------------------------
    | Rascunho de Parecer Descritivo com IA (Onda 30)
    |--------------------------------------------------------------------------
    |
    | O texto é montado a partir dos conceitos por habilidade e das evidências do
    | portfólio do aluno, que são dados pedagógicos de uma criança. Mesmo sem nome
    | (o aluno vai como "[ALUNO]" e o nome é recolocado localmente), o envio ao
    | provedor de IA segue a pendência M9: fica DESLIGADO por padrão até o plano
    | corporativo do provedor ser homologado.
    |
    */
    'parecer_ia_habilitado' => (bool) env('COPILOTO_PEDAGOGICO_PARECER_IA_HABILITADO', false),

    /*
    |--------------------------------------------------------------------------
    | Parâmetros de Inferência e Timeout
    |--------------------------------------------------------------------------
    |
    | Planos de aula completos e adaptações DUA exigem tempo maior de resposta.
    | O timeout recomendado é de 90 segundos.
    |
    */
    'timeout' => (int) env('COPILOTO_PEDAGOGICO_TIMEOUT', 90),
    'temperatura' => (float) env('COPILOTO_PEDAGOGICO_TEMPERATURA', 0.3),
    'max_output_tokens' => (int) env('COPILOTO_PEDAGOGICO_MAX_TOKENS', 8192),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting por Usuário
    |--------------------------------------------------------------------------
    |
    | Limite máximo de gerações por minuto por professor/coordenador.
    |
    */
    'limite_por_minuto' => (int) env('COPILOTO_PEDAGOGICO_LIMITE_POR_MINUTO', 10),

    /*
    |--------------------------------------------------------------------------
    | Persona e Diretrizes Pedagógicas Oficiais
    |--------------------------------------------------------------------------
    */
    'persona_plano_aula' => 'Você é um especialista em planejamento pedagógico alinhado à Base Nacional Comum Curricular (BNCC), Taxonomia de Bloom Revisada e Metodologias Ativas de Aprendizagem. Seu objetivo é estruturar planos de aula dinâmicos, exequíveis e rigorosamente fundamentados nas habilidades curriculares selecionadas pelo professor.',

    'persona_parecer' => 'Você é um(a) professor(a) experiente da educação básica brasileira, autor(a) de pareceres descritivos. Escreve com acolhimento e linguagem simples, valorizando o que o estudante já consegue fazer e indicando os próximos passos de aprendizagem. Nunca compara o estudante com colegas, não usa rótulos nem diagnósticos, não atribui notas e não inventa fatos: usa somente os registros recebidos.',

    'persona_pei' => 'Você é um consultor pedagógico especializado em Educação Especial Inclusiva, Desenho Universal para a Aprendizagem (DUA) e na Lei Brasileira de Inclusão (Lei nº 13.146/2015). Você propõe exclusivamente adaptações curriculares práticas, ambientais e metodológicas para garantir acessibilidade pedagógica, mantendo o objetivo e a habilidade curricular BNCC alvo da turma. Você NUNCA sugere diagnósticos, tratamentos clínicos, medicações ou laudos médicos.',
];

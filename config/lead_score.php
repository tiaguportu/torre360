<?php

// Ver docs/crm_lead_score.md para a explicação completa de cada fator.
return [

    // Pesos máximos de cada fator. A soma deve dar 100.
    'pesos' => [
        'percepcao_consultor' => 20,
        'filhos' => 10,
        'distancia' => 10,
        'transporte' => 5,
        'profissao' => 5,
        'valor_estimado' => 10,
        'interacoes_sucesso' => 10,
        'total_interacoes' => 5,
        'recencia' => 10,
        'completude_cadastro' => 5,
        'origem' => 5,
        'estagio_funil' => 5,
    ],

    // Faixas de cor exibidas na interface (>= quente, >= morno, abaixo disso frio).
    'faixas_cor' => [
        'quente' => 70,
        'morno' => 40,
    ],

    // Percepção do consultor (campo interessado.temperatura): fator de maior peso.
    'percepcao_consultor' => [
        'quente' => 20,
        'morno' => 10,
        'frio' => 0,
        'nao_informado' => 0,
    ],

    // Nº de filhos em idade escolar vinculados ao lead (dependentes).
    'filhos' => [
        ['minimo' => 3, 'pontos' => 10],
        ['minimo' => 2, 'pontos' => 7],
        ['minimo' => 1, 'pontos' => 3],
        ['minimo' => 0, 'pontos' => 0],
    ],

    // Faixa de distância até a escola (campo interessado.faixa_distancia_escola).
    'faixa_distancia_escola' => [
        'ate_2km' => 10,
        'de_2_a_5km' => 7,
        'de_5_a_10km' => 3,
        'mais_de_10km' => 0,
    ],

    // Meio de transporte utilizado (campo interessado.meio_transporte).
    'meio_transporte' => [
        'carro_proprio' => 5,
        'van_escolar' => 5,
        'a_pe_ou_bicicleta' => 3,
        'transporte_publico' => 2,
        'nao_informado' => 0,
    ],

    // Faixas de valor estimado de matrícula (interessado.valor_estimado).
    'valor_estimado' => [
        ['minimo' => 5000, 'pontos' => 10],
        ['minimo' => 3000, 'pontos' => 7],
        ['minimo' => 1500, 'pontos' => 4],
        ['minimo' => 0, 'pontos' => 1],
    ],

    // Classificação da profissão (pessoa.profissao, texto livre) por palavra-chave.
    // Comparação é feita sem acento e em minúsculas, usando "contém".
    'profissoes' => [
        'medic' => 5, 'advogad' => 5, 'engenh' => 5, 'empresari' => 5, 'diretor' => 5,
        'dentist' => 5, 'contador' => 4, 'gerente' => 4, 'analista' => 4, 'professor' => 4,
        'comerciante' => 3, 'autonomo' => 3, 'vendedor' => 3, 'motorista' => 2,
        'auxiliar' => 2, 'operador' => 2, 'do lar' => 2, 'aposentad' => 2, 'estudante' => 1,
        'desemprega' => 1,
    ],
    // Pontuação quando a profissão está vazia ou não bate com nenhuma palavra-chave.
    'profissao_padrao' => 2,

    // Peso por resultado de histórico de contato considerado "interação bem-sucedida".
    'interacoes_sucesso' => [
        'resultados' => ['agendou_visita', 'matriculou'],
        'pontos_por_interacao' => 5,
    ],

    // Peso por interação registrada (independente do resultado).
    'total_interacoes' => [
        'pontos_por_interacao' => 1,
    ],

    // Recência do último contato (dias desde o último histórico ou desde a criação do lead).
    'recencia' => [
        ['maximo_dias' => 3, 'pontos' => 10],
        ['maximo_dias' => 7, 'pontos' => 7],
        ['maximo_dias' => 15, 'pontos' => 4],
        ['maximo_dias' => 30, 'pontos' => 1],
        ['maximo_dias' => null, 'pontos' => 0],
    ],

    // Peso por origem do lead (nome cadastrado em origem_interessado.nome, comparado em minúsculas).
    'origem' => [
        'indicação' => 5,
        'indicacao' => 5,
        'google' => 3,
        'instagram' => 3,
        'facebook' => 3,
        'site' => 2,
    ],
    'origem_padrao' => 1,
];

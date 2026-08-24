<?php

// Ver docs/crm_lead_score.md para a explicação completa de cada fator.
return [

    // Pesos máximos de cada fator. A soma deve dar 100.
    'pesos' => [
        'filhos' => 15,
        'distancia' => 15,
        'transporte' => 5,
        'profissao' => 10,
        'valor_estimado' => 10,
        'interacoes_sucesso' => 15,
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

    // Nº de filhos em idade escolar vinculados ao lead (dependentes).
    'filhos' => [
        ['minimo' => 3, 'pontos' => 15],
        ['minimo' => 2, 'pontos' => 10],
        ['minimo' => 1, 'pontos' => 5],
        ['minimo' => 0, 'pontos' => 0],
    ],

    // Faixa de distância até a escola (campo interessado.faixa_distancia_escola).
    'faixa_distancia_escola' => [
        'ate_2km' => 15,
        'de_2_a_5km' => 10,
        'de_5_a_10km' => 5,
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
        'medic' => 10, 'advogad' => 10, 'engenh' => 10, 'empresari' => 10, 'diretor' => 9,
        'dentist' => 9, 'contador' => 8, 'gerente' => 8, 'analista' => 7, 'professor' => 7,
        'comerciante' => 6, 'autonomo' => 5, 'vendedor' => 5, 'motorista' => 4,
        'auxiliar' => 4, 'operador' => 4, 'do lar' => 3, 'aposentad' => 3, 'estudante' => 2,
        'desemprega' => 1,
    ],
    // Pontuação quando a profissão está vazia ou não bate com nenhuma palavra-chave.
    'profissao_padrao' => 3,

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

<?php

// Ver docs/risco_evasao_roadmap.md para a explicacao completa de cada fator.
return [

    // Pesos máximos de cada fator. A soma deve dar 100.
    'pesos' => [
        'frequencia' => 40,
        'desempenho' => 35,
        'inadimplencia' => 25,
    ],

    // Faixas de cor exibidas na interface (>= alto, >= moderado, abaixo disso baixo).
    'faixas_cor' => [
        'alto' => 60,
        'moderado' => 30,
    ],

    // % de faltas nos últimos 30 dias (sobre as aulas com frequência lançada no período),
    // em percentual inteiro (0-100). Vale a primeira faixa cujo mínimo o percentual atinge.
    'frequencia' => [
        ['minimo' => 30, 'pontos' => 40],
        ['minimo' => 20, 'pontos' => 25],
        ['minimo' => 10, 'pontos' => 10],
        ['minimo' => 0, 'pontos' => 0],
    ],

    // Situação final das disciplinas no período letivo mais recente já fechado para esta
    // matrícula (SituacaoFinalDisciplina). Vale a pior situação encontrada no período.
    'desempenho' => [
        'reprovado' => 35,
        'recuperacao' => 20,
        'aprovado' => 0,
    ],
];

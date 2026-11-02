<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Atendimento da família (Ondas 83, 80 e 34)
    |--------------------------------------------------------------------------
    |
    | O prazo do chamado é contado em horas úteis: só andam as horas dentro da janela abaixo, de segunda a sexta (dias 1 a 5).
    | O prazo de primeira resposta e o de resolução de cada setor ficam no cadastro do setor; os valores abaixo valem para o
    | setor que não os preencheu. Feriados não entram na conta.
    */

    'horario_comercial' => [
        'dias' => [1, 2, 3, 4, 5],
        'inicio' => env('ATENDIMENTO_HORARIO_INICIO', '08:00'),
        'fim' => env('ATENDIMENTO_HORARIO_FIM', '18:00'),
    ],

    'prazo_primeira_resposta_horas' => (int) env('ATENDIMENTO_PRAZO_PRIMEIRA_RESPOSTA_HORAS', 24),

    'prazo_resolucao_horas' => (int) env('ATENDIMENTO_PRAZO_RESOLUCAO_HORAS', 72),

    // Chamado "aguardando a família" há mais dias que isso fecha sozinho, com aviso.
    'fecha_aguardando_dias' => (int) env('ATENDIMENTO_FECHA_AGUARDANDO_DIAS', 7),

    // Pedido do titular (LGPD, art. 19, II): prazo em dias corridos.
    'prazo_pedido_titular_dias' => (int) env('ATENDIMENTO_PRAZO_PEDIDO_TITULAR_DIAS', 15),
];

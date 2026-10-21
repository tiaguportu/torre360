<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Informe anual de mensalidades (Onda 42)
    |--------------------------------------------------------------------------
    |
    | O informe lista o que a família pagou à escola no ano-calendário e estima a parte que costuma ser aceita como
    | despesa com instrução na declaração do imposto de renda (Lei nº 9.250/1995, art. 8º, II, b). A classificação é
    | feita pela DESCRIÇÃO do item da fatura: item cuja descrição contém uma destas palavras (sem acento, sem
    | diferenciar maiúsculas) é tratado como não dedutível. A lista é uma estimativa: confira com a contabilidade.
    |
    */
    'informe_anual' => [
        'palavras_nao_dedutiveis' => [
            'material',
            'uniforme',
            'alimenta',
            'lanche',
            'refeic',
            'transporte',
            'passeio',
            'excurs',
            'apostila',
        ],

        // Máximo de alunos no PDF em lote (evita estourar tempo e memória).
        'limite_lote' => 300,
    ],
];

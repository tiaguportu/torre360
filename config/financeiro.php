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

    /*
    |--------------------------------------------------------------------------
    | Encargos por atraso (Onda 62)
    |--------------------------------------------------------------------------
    |
    | Valores padrão da escola para a multa e os juros de mora das mensalidades. Cada contrato pode ter os seus
    | (campos do contrato); o que está aqui vale quando o contrato não define. O cálculo é do demonstrativo de débito
    | e das simulações de acordo e de cancelamento; o boleto emitido pelo banco ou pelo gateway deve ser configurado
    | com os mesmos percentuais.
    |
    | - multa_percentual: aplicada uma vez, sobre o saldo devedor. O CDC limita a multa de mora a 2% (art. 52, § 1º, na
    |   redação da Lei nº 9.298/1996): o sistema nunca usa mais que isso.
    | - juros_mensal_percentual: juros simples, proporcionais aos dias de atraso (mês de 30 dias).
    | - carencia_dias: dias de atraso em que ainda não se cobra nada (0 = cobra desde o primeiro dia depois do vencimento).
    | - feriados: datas (Y-m-d) em que o vencimento que cair nelas passa para o próximo dia útil, além dos fins de semana.
    |
    */
    'encargos' => [
        'multa_percentual' => 2.0,
        'juros_mensal_percentual' => 1.0,
        'carencia_dias' => 0,
        'feriados' => [],
    ],
];

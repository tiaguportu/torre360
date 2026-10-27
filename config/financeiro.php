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
    /*
    |--------------------------------------------------------------------------
    | Contas recorrentes e fluxo projetado (Onda 78)
    |--------------------------------------------------------------------------
    |
    | - dias_retroativos: a rotina que gera as contas olha também os vencimentos dos últimos N dias, para recuperar o que uma
    |   parada do agendador deixou para trás. Modelo novo com vigência muito antiga não gera conta de meses passados.
    | - aviso_reajuste_dias: a Mesa avisa o reajuste de contrato com N dias de antecedência.
    | - revisao_valor_dias: conta de valor estimado entra na fila de revisão N dias antes do vencimento.
    |
    | Fluxo projetado:
    | - saldo_base: soma ao saldo calculado pelas transações bancárias lançadas. Use quando o primeiro extrato importado não
    |   começa do zero (saldo anterior ao sistema). O saldo inicial errado estraga toda a projeção.
    | - taxa_padrao: parcela do que vence que costuma ser recebida, usada no cenário "média histórica" quando a escola ainda não
    |   tem faturas suficientes para calcular a sua.
    | - meses_historico: meses completos usados para calcular a taxa de recebimento.
    | - fator_atrasadas: parcela da taxa aplicada às faturas que já venceram e continuam abertas (no cenário "média histórica").
    | - dias_recebimento_atrasadas: em quantos dias, a partir de hoje, a projeção coloca o recebimento das faturas atrasadas.
    | - alerta_dias / urgente_dias: a Mesa avisa o saldo negativo previsto nos próximos N dias; dentro de urgente_dias fica atrasado.
    |
    */
    'recorrentes' => [
        'dias_retroativos' => 31,
        'aviso_reajuste_dias' => 30,
        'revisao_valor_dias' => 7,
    ],

    'projecao' => [
        'saldo_base' => (float) env('FINANCEIRO_SALDO_BASE', 0),
        'taxa_padrao' => 0.85,
        'meses_historico' => 6,
        'fator_atrasadas' => 0.5,
        'dias_recebimento_atrasadas' => 7,
        'dias' => 90,
        'alerta_dias' => 30,
        'urgente_dias' => 14,
    ],
    /*
    |--------------------------------------------------------------------------
    | Resultado realizado por turma (Onda 77)
    |--------------------------------------------------------------------------
    |
    | - meses_alerta: a Minha Mesa lista a turma com resultado negativo (e receita) em todos os últimos N meses completos.
    | - margem_critica: a turma fica "atrasada" na fila quando a margem do último mês é igual ou pior que este percentual.
    | - criterio_padrao: rateio dos custos indiretos usado pela fila da Mesa (aluno, turma ou hora_aula).
    |
    */
    'rentabilidade' => [
        'meses_alerta' => 3,
        'margem_critica' => -20.0,
        'criterio_padrao' => 'aluno',
    ],
];

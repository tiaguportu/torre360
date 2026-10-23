<?php

/*
|--------------------------------------------------------------------------
| Carga letiva e faltas excessivas (Ondas 49 e 57)
|--------------------------------------------------------------------------
|
| Os números abaixo vêm da LDB (Lei nº 9.394/1996) e variam por sistema de ensino: o conselho estadual ou municipal pode
| exigir mais. Confira com a secretaria de educação antes de confiar no semáforo, e ajuste aqui, não no código.
*/

return [

    /*
     * Etapa de cada turma, a partir do código da etapa agregada do Educacenso (`etapa_ensino_agregada.codigo`). Código que
     * não está aqui (EJA, educação profissional...) fica "sem mínimo definido": as horas e os dias são apurados, mas o
     * semáforo não compara com nada.
     */
    'etapas' => [
        '301' => 'infantil',
        '302' => 'fundamental',
        '303' => 'fundamental',
        '304' => 'medio',
        '305' => 'medio',
    ],

    // Etapa usada quando a turma não informa a etapa agregada.
    'etapa_padrao' => 'fundamental',

    /*
     * Mínimo anual por etapa, em horas de 60 minutos (hora-relógio, Parecer CNE/CEB nº 8/2004) e em dias de efetivo
     * trabalho escolar (LDB, art. 24, I e 31, II). O ensino médio caminha para 1.400 horas (Lei nº 14.945/2024): ajuste
     * conforme o cronograma do seu sistema de ensino.
     */
    'minimos' => [
        'infantil' => ['horas' => 800, 'dias' => 200],
        'fundamental' => ['horas' => 800, 'dias' => 200],
        'medio' => ['horas' => 1000, 'dias' => 200],
    ],

    /*
     * Como contar os dias letivos previstos:
     *  - cronograma: dias em que a turma tem aula no cronograma (sábado letivo só conta se houver aula);
     *  - calendario: dias da semana abaixo, do início ao fim do período, menos os dias não letivos e os feriados nacionais.
     */
    'modo_dias' => env('FREQUENCIA_MODO_DIAS', 'cronograma'),

    // 1 = segunda ... 7 = domingo (usado só no modo "calendario").
    'dias_da_semana' => [1, 2, 3, 4, 5],

    /*
     * Faltas. O aluno precisa de 75% de frequência (LDB, art. 24, VI): o percentual permitido de faltas é 25%. A escola
     * notifica o Conselho Tutelar quando as faltas passam de 30% desse permitido (LDB, art. 12, VIII), ou seja, 7,5% das
     * aulas previstas no ano.
     */
    'frequencia_minima' => 75.0,

    'notificacao' => [
        // 30% do percentual permitido de faltas.
        'percentual_do_permitido' => 30.0,

        // Quais faltas contam para o limite: "todas" (a letra da LDB) ou "injustificadas" (o ECA, art. 56, II).
        'criterio' => env('FREQUENCIA_CRITERIO_NOTIFICACAO', 'todas'),

        // O ofício só sai depois de pelo menos uma tentativa de contato com a família registrada.
        'exigir_contato_antes_do_oficio' => true,

        // Caso aberto sem contato com a família há mais de N dias, ou notificado sem resposta há mais de N dias, é atraso.
        'dias_sem_contato' => (int) env('FREQUENCIA_DIAS_SEM_CONTATO', 7),
        'dias_sem_resposta' => (int) env('FREQUENCIA_DIAS_SEM_RESPOSTA', 30),
    ],
];

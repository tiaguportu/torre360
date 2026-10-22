<?php

/*
|--------------------------------------------------------------------------
| Grade horária assistida (Onda 64)
|--------------------------------------------------------------------------
*/

return [

    /*
     * Dias da semana em que a escola dá aula (0 = domingo ... 6 = sábado). A sugestão de horários só procura lugar
     * nesses dias.
     */
    'dias_da_semana' => [1, 2, 3, 4, 5],

    /*
     * Máximo de aulas da mesma disciplina no mesmo dia que a sugestão aceita. Dois tempos seguidos fazem uma aula
     * dupla; mais que isso costuma cansar a turma.
     */
    'maximo_aulas_por_dia_da_disciplina' => 2,

    /*
     * Se a sugestão deve colocar também as disciplinas da turma que ainda não têm professor definido. Desligado, elas
     * ficam como "não alocadas" com o aviso para definir o professor: sem ele, a sugestão não tem como evitar conflitos.
     */
    'sugerir_sem_professor' => false,
];

<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Agendamento online de visitas (Onda 86)
    |--------------------------------------------------------------------------
    */

    // Envios da página pública por origem dentro da janela (a chave do limite usa só o hash do IP).
    'max_agendamentos' => (int) env('VISITAS_MAX_AGENDAMENTOS', 5),

    'janela_minutos' => (int) env('VISITAS_JANELA_MINUTOS', 60),

    // Quantos dias à frente a página mostra, no máximo, mesmo que o consultor permita mais.
    'dias_a_exibir' => (int) env('VISITAS_DIAS_A_EXIBIR', 21),

    // Cancelar ou reagendar com menos horas que isto avisa o consultor em destaque.
    'aviso_cancelamento_horas' => (int) env('VISITAS_AVISO_CANCELAMENTO_HORAS', 4),

    // Texto da confirmação e do lembrete.
    'o_que_levar' => env('VISITAS_O_QUE_LEVAR', 'Traga um documento com foto. Se quiser, leve também o histórico escolar do estudante.'),
];

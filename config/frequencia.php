<?php

/*
|--------------------------------------------------------------------------
| Frequência escolar: aviso de faltas à família
|--------------------------------------------------------------------------
|
| A falta lançada não gera mais um e-mail por aula. Ela fica marcada como pendente e o comando `frequencia:enviar-resumo-diario`
| junta tudo o que está pendente em um único aviso por responsável (e-mail, sino e push), uma vez por dia.
*/

return [
    // Horário (HH:MM, fuso da aplicação) em que o resumo do dia sai. Falta lançada depois dele entra no resumo do dia seguinte.
    'resumo_horario' => env('FALTAS_RESUMO_HORARIO', '18:00'),
];

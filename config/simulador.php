<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Simulador do próximo ano letivo (Onda 76)
    |--------------------------------------------------------------------------
    |
    | - padroes: os parâmetros com que o simulador abre.
    |     taxa_andamento: % das rematrículas já iniciadas (e ainda não confirmadas) que costumam terminar.
    |     taxa_nao_iniciadas: % dos alunos que ainda nem iniciaram a rematrícula que costumam renová-la.
    |     corte_risco: aluno ainda não confirmado com risco de evasão igual ou acima deste valor (0 a 100) é tirado da conta.
    |     incluir_espera: soma a lista de espera, até o limite das vagas que sobram.
    |     reajuste_percentual: reajuste extra sobre o preço da série, para o "e se" (0 usa o preço como está).
    | - capacidade_padrao: vagas por turma quando a série ainda não tem turma no período de destino nem capacidade informada.
    | - alerta_prazo_dias: a fila da Minha Mesa marca como atrasado o problema de uma série quando a campanha de rematrícula termina
    |   em até N dias (a decisão de abrir ou fechar turma precisa sair antes).
    |
    */
    'padroes' => [
        'taxa_andamento' => 90.0,
        'taxa_nao_iniciadas' => 60.0,
        'corte_risco' => 60,
        'incluir_espera' => true,
        'reajuste_percentual' => 0.0,
    ],

    'capacidade_padrao' => 30,

    'alerta_prazo_dias' => 15,
];

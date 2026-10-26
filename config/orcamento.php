<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Orçamento anual × realizado (Onda 43)
    |--------------------------------------------------------------------------
    */

    // Despesa que chega a este percentual do previsto acumulado fica em atenção (amarela); acima de 100% está estourada. Na receita é o
    // inverso: abaixo de 100% do previsto é atenção, e abaixo deste percentual é estouro.
    'alerta_percentual' => (int) env('ORCAMENTO_ALERTA_PERCENTUAL', 90),
];

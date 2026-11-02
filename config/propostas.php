<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Proposta comercial com aceite da família (Onda 87)
    |--------------------------------------------------------------------------
    */

    // Validade, em dias a partir de hoje, de uma proposta expirada que o consultor renova.
    'renovacao_dias' => (int) env('PROPOSTAS_RENOVACAO_DIAS', 5),
];

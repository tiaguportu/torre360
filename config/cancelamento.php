<?php

/*
|--------------------------------------------------------------------------
| Cancelamento de matrícula e distrato (Onda 61)
|--------------------------------------------------------------------------
*/

return [

    /*
     * Exige a cópia assinada do distrato anexada antes de concluir o cancelamento. Desligue se a escola aceita o pedido
     * da família (que já é um registro por escrito) como suficiente.
     */
    'exige_distrato_assinado' => true,

    /*
     * Dias de antecedência mínima entre o pedido da família e a data de saída. O contrato costuma prever aviso prévio;
     * 0 deixa a família escolher qualquer data a partir de hoje. A secretaria registra datas anteriores sem esta regra.
     */
    'antecedencia_minima_dias' => 0,

    /*
     * Prazo, em dias a partir da conclusão, para pagar a multa rescisória quando a secretaria a cobra. A multa só pode
     * ser cobrada se o contrato a previr e não pode ser abusiva (CDC, art. 51, IV).
     */
    'multa_vencimento_dias' => 10,
];

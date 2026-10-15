<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Limiares de Detecção OMR (Preenchimento das Bolhas)
    |--------------------------------------------------------------------------
    |
    | Fração de pixels escuros dentro da máscara da bolha normalizada:
    | - Acima de limiar_marcada: considerada preenchida.
    | - Abaixo de limiar_vazia: considerada vazia / em branco.
    | - Entre limiar_vazia e limiar_marcada: considerada ambígua (exige conferência).
    |
    */
    'limiar_marcada' => (float) env('OMR_LIMIAR_MARCADA', 0.42),
    'limiar_vazia' => (float) env('OMR_LIMIAR_VAZIA', 0.18),

    /*
    |--------------------------------------------------------------------------
    | Retenção e Limpeza de Arquivos
    |--------------------------------------------------------------------------
    |
    | Dias de retenção das fotos originais antes da purga automática.
    |
    */
    'retencao_dias' => (int) env('OMR_RETENCAO_DIAS', 30),

    /*
    |--------------------------------------------------------------------------
    | Limite de Arquivos por Lote
    |--------------------------------------------------------------------------
    |
    | Quantidade máxima de fotos aceitas em um único envio de lote do professor.
    |
    */
    'max_arquivos_lote' => (int) env('OMR_MAX_ARQUIVOS', 45),

    /*
    |--------------------------------------------------------------------------
    | Fila de Processamento
    |--------------------------------------------------------------------------
    |
    | Nome da fila para os jobs assíncronos de OMR.
    |
    */
    'fila' => env('OMR_FILA', 'default'),
];

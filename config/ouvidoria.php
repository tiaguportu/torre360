<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Ouvidoria e convivência escolar (Ondas 35 e 63)
    |--------------------------------------------------------------------------
    |
    | Prazos em dias corridos para a primeira providência do comitê. A suspeita de violência contra criança ou adolescente tem
    | prazo menor, porque a comunicação ao Conselho Tutelar não pode esperar (ECA, art. 13).
    */

    'prazo_dias' => (int) env('OUVIDORIA_PRAZO_DIAS', 10),

    'prazo_suspeita_violencia_dias' => (int) env('OUVIDORIA_PRAZO_SUSPEITA_VIOLENCIA_DIAS', 2),

    // Formulário público: envios por IP dentro da janela (o limite vem do cache; o IP não é gravado com a denúncia).
    'max_envios' => (int) env('OUVIDORIA_MAX_ENVIOS', 5),

    'janela_minutos' => (int) env('OUVIDORIA_JANELA_MINUTOS', 30),

    // Consultas ao acompanhamento por código (protege contra tentativa de adivinhar o código).
    'max_consultas' => (int) env('OUVIDORIA_MAX_CONSULTAS', 10),
];

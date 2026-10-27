<?php

use App\Services\Relatorios\Fontes\CasosDeFaltas;
use App\Services\Relatorios\Fontes\ContasAPagar;
use App\Services\Relatorios\Fontes\DocumentosParaAssinatura;
use App\Services\Relatorios\Fontes\FluxoProjetadoSemanal;
use App\Services\Relatorios\Fontes\Inadimplencia;
use App\Services\Relatorios\Fontes\MatriculasPorTurma;
use App\Services\Relatorios\Fontes\MovimentoBancario;
use App\Services\Relatorios\Fontes\RentabilidadeTurmas;

return [
    /*
    |--------------------------------------------------------------------------
    | Fontes dos relatórios configuráveis (Onda 72)
    |--------------------------------------------------------------------------
    |
    | Cada classe implementa App\Contracts\FonteDeRelatorio. Um módulo novo que queira aparecer nos relatórios acrescenta a sua
    | classe aqui; a tela oferece só as fontes cuja permissão a pessoa tem.
    */
    'fontes' => [
        Inadimplencia::class,
        MatriculasPorTurma::class,
        ContasAPagar::class,
        MovimentoBancario::class,
        CasosDeFaltas::class,
        DocumentosParaAssinatura::class,
        FluxoProjetadoSemanal::class,
        RentabilidadeTurmas::class,
    ],

    // Máximo de linhas de um relatório em planilha e em PDF. Passou do limite, o arquivo sai cortado e a execução avisa.
    'limite_linhas' => (int) env('RELATORIOS_LIMITE_LINHAS', 20000),
    'limite_linhas_pdf' => (int) env('RELATORIOS_LIMITE_LINHAS_PDF', 1500),

    // Dias que o arquivo gerado fica no servidor antes de ser apagado (o registro da execução permanece).
    'retencao_dias' => (int) env('RELATORIOS_RETENCAO_DIAS', 30),

    // Máximo de relatórios agendados executados por rodada do agendador (a rodada é a cada 5 minutos).
    'limite_por_rodada' => (int) env('RELATORIOS_LIMITE_POR_RODADA', 10),
];

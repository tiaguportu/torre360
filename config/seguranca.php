<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Matrícula Online (formulário público)
    |--------------------------------------------------------------------------
    |
    | O wizard /matricular-online é aberto à internet e cria pessoas, matrículas,
    | contratos e contas de acesso. Estes limites seguram automações e abuso
    | (cada envio com documento ainda dispara uma análise por IA, que tem custo).
    |
    */

    'matricula_online' => [
        // Tentativas de finalizar a matrícula por IP dentro da janela abaixo.
        'max_tentativas' => (int) env('MATRICULA_ONLINE_MAX_TENTATIVAS', 6),
        'janela_minutos' => (int) env('MATRICULA_ONLINE_JANELA_MINUTOS', 60),

        // Validade do link da tela de confirmação (assinado): ela mostra dados do aluno e do responsável.
        'link_sucesso_horas' => (int) env('MATRICULA_ONLINE_LINK_SUCESSO_HORAS', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Content-Security-Policy
    |--------------------------------------------------------------------------
    |
    | Duas camadas (ver App\Support\ContentSecurityPolicy):
    |  - "base" é SEMPRE aplicada (object-src, base-uri, frame-ancestors): não depende de lista de origens.
    |  - "estrita" restringe de onde scripts, estilos, imagens e conexões podem vir. Começa em modo
    |    "report-only" (só registra violações em /api/csp-report, sem bloquear nada); depois de uma
    |    semana sem violações legítimas, troque CSP_MODO=enforce.
    |
    | CSP_MODO: off | report-only | enforce
    |
    */

    'csp' => [
        'modo' => env('CSP_MODO', 'report-only'),

        // Origens extras (separadas por vírgula) que a política estrita deve aceitar, por diretiva.
        'extra' => [
            'script-src' => env('CSP_EXTRA_SCRIPT_SRC', ''),
            'style-src' => env('CSP_EXTRA_STYLE_SRC', ''),
            'img-src' => env('CSP_EXTRA_IMG_SRC', ''),
            'font-src' => env('CSP_EXTRA_FONT_SRC', ''),
            'connect-src' => env('CSP_EXTRA_CONNECT_SRC', ''),
            'frame-src' => env('CSP_EXTRA_FRAME_SRC', ''),
        ],
    ],

];

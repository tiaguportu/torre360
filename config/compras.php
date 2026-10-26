<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Compras e cotações (Onda 38)
    |--------------------------------------------------------------------------
    */

    // Alçadas de aprovação do pedido pelo valor total. A primeira faixa cujo `ate` comporta o valor define o nível exigido; quem tem
    // a permissão desse nível ou de um nível acima aprova. `ate = null` é a faixa sem teto. Quem solicitou nunca aprova a própria compra.
    'alcadas' => [
        ['nivel' => 1, 'ate' => (float) env('COMPRAS_ALCADA_1_ATE', 1000), 'permissao' => 'AprovarNivel1:PedidoCompra', 'nome' => 'Coordenação'],
        ['nivel' => 2, 'ate' => (float) env('COMPRAS_ALCADA_2_ATE', 10000), 'permissao' => 'AprovarNivel2:PedidoCompra', 'nome' => 'Direção'],
        ['nivel' => 3, 'ate' => null, 'permissao' => 'AprovarNivel3:PedidoCompra', 'nome' => 'Mantenedora'],
    ],

    // Mínimo de cotações pelo valor da cotação escolhida. Com menos que isso, quem escolhe precisa justificar a dispensa.
    'cotacoes_minimas' => [
        ['ate' => (float) env('COMPRAS_COTACAO_1_ATE', 500), 'minimo' => 1],
        ['ate' => (float) env('COMPRAS_COTACAO_2_ATE', 5000), 'minimo' => 2],
        ['ate' => null, 'minimo' => 3],
    ],

    // Compra acima do saldo do orçamento: false pede a justificativa de quem aprova; true bloqueia a aprovação.
    'bloquear_acima_do_orcamento' => (bool) env('COMPRAS_BLOQUEAR_ACIMA_DO_ORCAMENTO', false),

    // Segregação de funções: quem solicita não aprova e quem aprova não recebe. Só desligue em escola em que uma pessoa faz tudo,
    // sabendo que o controle interno deixa de existir.
    'segregar_funcoes' => (bool) env('COMPRAS_SEGREGAR_FUNCOES', true),

    // Fornecedor com recebimento divergente nos últimos N meses vem sinalizado no mapa de cotações.
    'meses_historico_fornecedor' => (int) env('COMPRAS_MESES_HISTORICO_FORNECEDOR', 12),
];

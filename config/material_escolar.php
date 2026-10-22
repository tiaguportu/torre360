<?php

/*
|--------------------------------------------------------------------------
| Lista de material escolar (Onda 59)
|--------------------------------------------------------------------------
|
| A Lei nº 9.870/1999, art. 1º, § 7º (incluído pela Lei nº 12.886/2013), torna nula a cláusula que obriga a família a pagar
| ou fornecer material escolar de uso COLETIVO dos estudantes ou da escola; o custo entra na anuidade. Os termos abaixo
| são os que os órgãos de defesa do consumidor costumam apontar em listas abusivas. São uma ajuda para quem monta a
| lista, não uma decisão jurídica: confira com o jurídico da escola.
|
| Os termos são comparados sem acento e sem diferença de maiúsculas, no início de uma palavra ("sabao" pega "sabão em pó").
|
*/

return [

    /*
    | Uso coletivo evidente: o item NÃO entra na lista, não há como confirmar.
    */
    'termos_bloqueados' => [
        'papel higienico',
        'papel toalha',
        'toalha de papel',
        'sabao',
        'detergente',
        'desinfetante',
        'agua sanitaria',
        'cloro',
        'material de limpeza',
        'produto de limpeza',
        'produtos de limpeza',
        'pano de chao',
        'vassoura',
        'saco de lixo',
        'copo descartavel',
        'copos descartaveis',
        'agua mineral',
        'giz',
        'apagador',
        'pincel para quadro',
        'pincel de quadro',
        'marcador para quadro',
        'marcador de quadro',
        'toner',
        'cartucho',
        'grampeador',
        'fosforo',
        'alcool',
    ],

    /*
    | Pode ser de uso individual ou coletivo, conforme a quantidade e o fim: entra na lista só com a confirmação, por
    | escrito, de quem monta, de que é de uso individual do aluno e em quantidade coerente com as atividades.
    */
    'termos_atencao' => [
        'resma',
        'papel sulfite',
        'papel a4',
        'folha sulfite',
        'fita adesiva',
        'durex',
        'clips',
        'clipes',
        'grampo',
        'percevejo',
        'tachinha',
        'barbante',
        'algodao',
        'verniz',
        'sabonete',
        'lenco umedecido',
        'pilha',
    ],

    /*
    | Expressões que contêm um termo bloqueado mas são material individual comum: "giz de cera" não é giz de lousa.
    */
    'excecoes' => [
        'giz de cera',
        'giz pastel',
    ],

    /*
    | Quantidade a partir da qual o item exige a confirmação de que é coerente com o uso individual do aluno.
    */
    'quantidade_sem_confirmacao' => 30,

];

<?php

/*
|--------------------------------------------------------------------------
| Reuniões e atas (Ondas 27 e 32)
|--------------------------------------------------------------------------
|
| O que a escola pode ajustar sem mexer em código: os itens de deliberação do conselho de classe, quantos dias a ata pode ficar em
| aberto antes de aparecer na Mesa de pendências e quantos dias de atraso o encaminhamento tem antes de a coordenação ser avisada.
*/

return [
    // Itens de deliberação do conselho de classe: código => rótulo. A deliberação só registra a decisão; não altera nota nem matrícula.
    'deliberacoes' => [
        'sem_pendencias' => 'Segue sem pendências',
        'destaque' => 'Destaque a reconhecer',
        'acompanhar' => 'Acompanhamento pedagógico',
        'recuperacao' => 'Indicar recuperação',
        'chamar_familia' => 'Chamar a família',
        'apoio_especializado' => 'Encaminhar a apoio especializado',
    ],

    // Deliberações que pedem um encaminhamento com responsável e prazo.
    'deliberacoes_com_encaminhamento' => ['acompanhar', 'recuperacao', 'chamar_familia', 'apoio_especializado'],

    // Dias, a partir da reunião realizada, até a ata em aberto ser cobrada na Mesa de pendências.
    'ata_prazo_dias' => 5,

    // Prazo sugerido (em dias) de um encaminhamento criado a partir da deliberação do conselho.
    'encaminhamento_prazo_dias' => 15,

    // Duração padrão (em minutos) usada quando a reunião é agendada só com a hora de início.
    'duracao_padrao_minutos' => [
        'conselho_classe' => 90,
        'pedagogica' => 60,
        'devolutiva' => 30,
        'formacao' => 120,
        'outra' => 60,
    ],

    // Janela (em dias) das ocorrências disciplinares que entram na fotografia do conselho.
    'janela_ocorrencias_dias' => 120,
];

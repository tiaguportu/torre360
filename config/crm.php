<?php

// Ver docs/crm_followup_whatsapp.md (alertas) e docs/crm_funil_e_captacao.md (funil e captação).
return [

    /*
    |--------------------------------------------------------------------------
    | Alertas diários de acompanhamento (`crm:notificar-pendentes`)
    |--------------------------------------------------------------------------
    */
    'alertas' => [
        // Intervalo mínimo, em dias, entre dois avisos do mesmo lead ao consultor enquanto ele
        // continua atrasado/estagnado. Evita e-mail + sino + activity log todo dia para o mesmo lead.
        'intervalo_dias' => (int) env('CRM_ALERTA_INTERVALO_DIAS', 3),

        // Lead atrasado (ou estagnado além do limite de estagnação) há tantos dias é levado também
        // à gestão (admin/super_admin), num único resumo por execução.
        'escalonar_apos_dias' => (int) env('CRM_ALERTA_ESCALONAR_APOS_DIAS', 7),

        // Lead ativo sem consultor há mais de X horas entra no resumo diário enviado à gestão.
        'sem_consultor_apos_horas' => (int) env('CRM_ALERTA_SEM_CONSULTOR_HORAS', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | Formulário público de captação
    |--------------------------------------------------------------------------
    */
    'captacao' => [
        // O e-mail de agradecimento é enviado no máximo uma vez por pessoa dentro desta janela (em
        // horas): sem isso, o formulário público poderia ser usado para encher a caixa de entrada de terceiros.
        'agradecimento_janela_horas' => 24,
    ],

    /*
    |--------------------------------------------------------------------------
    | Permissão que define quem pode ser "consultor responsável" por um lead
    |--------------------------------------------------------------------------
    | Usuários com esta permissão (direta ou por papel), além de admin e super_admin, aparecem nas
    | listas de consultor. Contas de famílias, professores etc. não entram.
    */
    'permissao_consultor' => 'Update:Interessado',
];

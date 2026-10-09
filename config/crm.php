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
    | Kanban (Funil de Vendas)
    |--------------------------------------------------------------------------
    */
    'kanban' => [
        // Cards carregados por coluna; o botão "Carregar mais" soma este valor a cada clique.
        'cards_por_coluna' => (int) env('CRM_KANBAN_CARDS_POR_COLUNA', 30),

        // Teto de cards por coluna mesmo com "Carregar mais" (a tela precisa continuar leve).
        'cards_maximo_por_coluna' => 300,

        // Colunas finais (matriculado/perdido) mostram só leads movidos nos últimos N dias; os mais
        // antigos continuam na listagem (aba "Finalizados").
        'dias_finalizados' => (int) env('CRM_KANBAN_DIAS_FINALIZADOS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Contadores (abas da listagem e selo do menu)
    |--------------------------------------------------------------------------
    | Os contadores são guardados em cache por este tempo (segundos) e descartados na hora quando um
    | lead, um contato ou uma etapa do funil é gravado. O score recalculado em lote não os descarta.
    */
    'contadores' => [
        'cache_segundos' => (int) env('CRM_CONTADORES_CACHE_SEGUNDOS', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Calendário de follow-up (rodapé da listagem)
    |--------------------------------------------------------------------------
    | Só entram follow-ups e visitas dentro desta janela, em dias a partir de hoje. Contatos atrasados
    | há mais tempo continuam na aba "Precisa de contato".
    */
    'calendario' => [
        'janela_passado_dias' => 90,
        'janela_futuro_dias' => 180,
    ],

    /*
    |--------------------------------------------------------------------------
    | Fila da IA
    |--------------------------------------------------------------------------
    | Análises de documento por IA (Gemini) rodam numa fila própria: uma chamada pode levar minutos
    | e não pode atrasar e-mails e notificações. O agendador (routes/console.php) mantém um worker
    | dedicado para esta fila.
    */
    'fila_ia' => env('CRM_FILA_IA', 'ia'),

    /*
    |--------------------------------------------------------------------------
    | Permissão que define quem pode ser "consultor responsável" por um lead
    |--------------------------------------------------------------------------
    | Usuários com esta permissão (direta ou por papel), além de admin e super_admin, aparecem nas
    | listas de consultor. Contas de famílias, professores etc. não entram.
    */
    'permissao_consultor' => 'Update:Interessado',
];

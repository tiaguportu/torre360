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
    | Régua de follow-up (`crm:executar-regua-follow-up`, roda de hora em hora)
    |--------------------------------------------------------------------------
    */
    'regua' => [
        // Gatilhos por data pós-evento (cadastro, visita realizada/faltou, contato atrasado) também valem
        // para eventos de até N dias atrás que ainda não receberam a mensagem (agendador parado, deploy).
        // Textos da régua que dizem "ontem" saem errados quando o envio atrasa: prefira {{DATA_VISITA}}.
        'janela_recuperacao_dias' => (int) env('CRM_REGUA_JANELA_RECUPERACAO_DIAS', 2),

        // Máximo de e-mails da régua por lead no mesmo dia (0 = sem limite). O excedente sai nos dias seguintes.
        'max_emails_por_lead_dia' => (int) env('CRM_REGUA_MAX_EMAILS_POR_LEAD_DIA', 2),

        // Falhas de envio (e-mail inválido, SMTP recusado) por regra e evento antes de desistir; uma tentativa por dia.
        'max_tentativas_falha' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | LGPD
    |--------------------------------------------------------------------------
    */
    'lgpd' => [
        // Identifica o texto de consentimento do formulário público que a família aceitou (gravado com o aceite).
        // Mude quando o texto mudar de significado, para saber quem aceitou qual versão.
        'versao_consentimento' => '2026-10',

        // Opcionais: mostrados no aviso do formulário público quando preenchidos. A escola define o texto da
        // Política de Privacidade e o canal do titular (Encarregado/DPO); o sistema não escreve a política.
        'url_politica_privacidade' => env('CRM_URL_POLITICA_PRIVACIDADE'),
        'contato_privacidade' => env('CRM_CONTATO_PRIVACIDADE'),

        // Rascunho de pré-matrícula (CPF, endereço e dados da família preenchidos no convite) é apagado
        // após N dias sem atualização se o lead não virou matrícula. `crm:expurgar-rascunhos-pre-matricula`.
        'retencao_rascunho_dias' => (int) env('CRM_RETENCAO_RASCUNHO_DIAS', 90),
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

    /*
    |--------------------------------------------------------------------------
    | Previsão de receita e inteligência comercial (Lote D2)
    |--------------------------------------------------------------------------
    */
    'previsao_receita' => [
        // Ticket médio de referência quando o lead não possui valor_estimado informado
        'ticket_medio_padrao' => (float) env('CRM_TICKET_MEDIO_PADRAO', 1500.00),

        // Probabilidade de conversão padrão e por etapa (%)
        'probabilidade_padrao' => (float) env('CRM_PROBABILIDADE_PADRAO', 20.0),
        'probabilidades_etapa' => [
            'Novo' => 10.0,
            'Em Atendimento' => 25.0,
            'Visita Agendada' => 50.0,
            'Proposta' => 75.0,
        ],
    ],
];

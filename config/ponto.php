<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Registro eletrônico de ponto (Onda 41)
    |--------------------------------------------------------------------------
    |
    | Este módulo NASCE no modo "controle interno": registra e apura a jornada de forma
    | íntegra (sequência de registros encadeada por hash, ajustes sem alterar o original),
    | mas NÃO é, por si só, um REP-P nem um REP-A nos termos da Portaria MTP nº 671/2021.
    | Os modos "rep_a" e "rep_p" só podem ser ativados quando todos os requisitos legais
    | forem atendidos (veja ConformidadePontoService e docs/ponto_banco_horas_roadmap.md).
    |
    */

    /*
    | Requisitos que dependem do DESENVOLVEDOR do sistema, não do cliente. Cada um só pode
    | virar `true` depois de ser realmente implementado e validado; trocar o valor sem isso
    | é declarar conformidade falsa perante a fiscalização do trabalho.
    */
    'requisitos_desenvolvedor' => [
        // Arquivo Fonte de Dados no leiaute oficial (Anexo V da Portaria MTP 671/2021).
        'afd_oficial' => false,
        // Arquivo Eletrônico de Jornada no leiaute oficial (Anexo VI).
        'aej_oficial' => false,
        // Requisitos do REP-P (Anexo IX) validados item a item, inclusive o Atestado Técnico e Termo de Responsabilidade (Anexo VII).
        'anexo_ix_validado' => false,
        // Assinatura eletrônica ICP-Brasil do comprovante e dos arquivos (implementação de AssinadorPonto).
        'assinatura_icp_brasil' => false,
    ],

    // Implementação de AssinadorPonto: "nenhum" (padrão, comprovante sem assinatura) ou, no futuro, "icp_brasil".
    'assinador' => env('PONTO_ASSINADOR', 'nenhum'),

    // Fonte da hora: "ntp" (servidores NTP.br, sincronizados com a Hora Legal Brasileira) ou "nenhuma" (não verifica).
    'hora_legal' => env('PONTO_HORA_LEGAL', 'ntp'),
    'servidores_ntp' => array_values(array_filter(array_map('trim', explode(',', (string) env('PONTO_SERVIDORES_NTP', 'a.ntp.br,b.ntp.br,c.ntp.br'))))),
    'ntp_timeout_segundos' => (int) env('PONTO_NTP_TIMEOUT', 2),

    // Variação máxima aceita entre o relógio do servidor e a Hora Legal Brasileira (requisito do Anexo IX: 30 segundos).
    'limite_desvio_segundos' => 30,

    // Um segundo toque do mesmo funcionário dentro dessa janela devolve o registro anterior (anti-duplo-clique).
    // Não é restrição de horário: o art. 74 da Portaria proíbe restringir quando o trabalhador pode marcar.
    'duplo_clique_segundos' => 10,

    // Tolerância da CLT, art. 58, § 1º: variações de até 5 min por marcação, no máximo 10 min por dia. Como a jornada é
    // cadastrada em minutos por dia (sem horário de entrada e saída), a tolerância é aplicada sobre o saldo do dia.
    'tolerancia_diaria_minutos' => 10,

    // Estabelecimentos com mais de 20 trabalhadores são obrigados a registrar a jornada (CLT, art. 74, § 2º).
    'limite_obrigatoriedade_trabalhadores' => 20,

    // Guarda mínima recomendada, pelo prazo prescricional trabalhista (CF, art. 7º, XXIX): o sistema nunca apaga registros.
    'retencao_minima_anos' => 5,
];

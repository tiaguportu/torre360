<?php

use App\Services\Pendencias\Fontes\AssinaturasAtrasadas;
use App\Services\Pendencias\Fontes\AtasPorFechar;
use App\Services\Pendencias\Fontes\CancelamentosParaAnalisar;
use App\Services\Pendencias\Fontes\CancelamentosParaConcluir;
use App\Services\Pendencias\Fontes\ClassificacaoContabilPendente;
use App\Services\Pendencias\Fontes\ComprasParaCotar;
use App\Services\Pendencias\Fontes\ContatosDoDia;
use App\Services\Pendencias\Fontes\DevolucoesPendentes;
use App\Services\Pendencias\Fontes\DocumentosParaAssinatura;
use App\Services\Pendencias\Fontes\EncaminhamentosDeReuniao;
use App\Services\Pendencias\Fontes\EncaminhamentosVencidosDaEquipe;
use App\Services\Pendencias\Fontes\EstoqueAbaixoDoMinimo;
use App\Services\Pendencias\Fontes\GradesIncompletas;
use App\Services\Pendencias\Fontes\LeadsQuentesParados;
use App\Services\Pendencias\Fontes\LeadsSemConsultor;
use App\Services\Pendencias\Fontes\LeadsSemProximaAcao;
use App\Services\Pendencias\Fontes\OrcamentoEstourado;
use App\Services\Pendencias\Fontes\PedidosParaAprovar;
use App\Services\Pendencias\Fontes\PedidosParaReceber;
use App\Services\Pendencias\Fontes\ProjecaoDoProximoAno;
use App\Services\Pendencias\Fontes\ProvidenciasRhVencidas;
use App\Services\Pendencias\Fontes\ReajustesDeContrato;
use App\Services\Pendencias\Fontes\RecorrentesParaRevisar;
use App\Services\Pendencias\Fontes\RematriculasAguardandoTurma;
use App\Services\Pendencias\Fontes\SaldoProjetadoNegativo;
use App\Services\Pendencias\Fontes\TransferenciasEmAndamento;
use App\Services\Pendencias\Fontes\TurmasComResultadoNegativo;
use App\Services\Pendencias\Fontes\VisitasDoDia;

return [
    /*
    |--------------------------------------------------------------------------
    | Filas da Mesa de pendências (Onda 71)
    |--------------------------------------------------------------------------
    |
    | Cada classe implementa App\Contracts\FontePendencia. Um módulo novo que tenha uma fila acrescenta a sua classe aqui;
    | a Mesa mostra só as filas que o papel ativo de cada usuário pode ver (a permissão vem da própria fila).
    */
    'fontes' => [
        CancelamentosParaAnalisar::class,
        CancelamentosParaConcluir::class,
        DevolucoesPendentes::class,
        DocumentosParaAssinatura::class,
        AssinaturasAtrasadas::class,
        RematriculasAguardandoTurma::class,
        TransferenciasEmAndamento::class,
        ProvidenciasRhVencidas::class,
        GradesIncompletas::class,
        ClassificacaoContabilPendente::class,
        // Mesa do consultor (Onda 84): filas do CRM.
        ContatosDoDia::class,
        VisitasDoDia::class,
        LeadsSemProximaAcao::class,
        LeadsQuentesParados::class,
        LeadsSemConsultor::class,
        // Suprimentos (Ondas 16, 38 e 43): compras, estoque e orçamento.
        ComprasParaCotar::class,
        PedidosParaAprovar::class,
        PedidosParaReceber::class,
        EstoqueAbaixoDoMinimo::class,
        OrcamentoEstourado::class,
        // Contas recorrentes e fluxo projetado (Onda 78).
        RecorrentesParaRevisar::class,
        ReajustesDeContrato::class,
        SaldoProjetadoNegativo::class,
        // Rentabilidade por turma (Onda 77).
        TurmasComResultadoNegativo::class,
        // Simulador do próximo ano letivo (Onda 76).
        ProjecaoDoProximoAno::class,
        // Reuniões e atas (Ondas 27 e 32): encaminhamentos de cada um, atas por fechar e o painel da coordenação.
        EncaminhamentosDeReuniao::class,
        AtasPorFechar::class,
        EncaminhamentosVencidosDaEquipe::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Prazos das filas sem prazo próprio
    |--------------------------------------------------------------------------
    */
    // Pedido de cancelamento sem resposta da secretaria há mais de N dias passa a "atrasado".
    'cancelamento_resposta_dias' => (int) env('MESA_CANCELAMENTO_RESPOSTA_DIAS', 3),

    // Cancelamento concluído com dinheiro a devolver, sem a devolução registrada há mais de N dias.
    'devolucao_dias' => (int) env('MESA_DEVOLUCAO_DIAS', 10),

    // Documento em rascunho (montado e não enviado) há mais de N dias.
    'rascunho_documento_dias' => (int) env('MESA_RASCUNHO_DOCUMENTO_DIAS', 2),

    // Documento enviado para assinatura sem todas as assinaturas há mais de N dias.
    'assinatura_atraso_dias' => (int) env('MESA_ASSINATURA_ATRASO_DIAS', 7),

    // Documento enviado cujo prazo termina em até N dias também entra na fila de assinaturas atrasadas.
    'assinatura_prazo_dias' => (int) env('MESA_ASSINATURA_PRAZO_DIAS', 3),

    // Rematrícula com os dados confirmados e sem turma definida há mais de N dias passa a "atrasada".
    'rematricula_turma_dias' => (int) env('MESA_REMATRICULA_TURMA_DIAS', 5),

    // Dia do mês a partir do qual a classificação contábil do mês anterior, ainda pendente, passa a "atrasada" (o escritório
    // costuma fechar o mês na primeira quinzena).
    'contabilidade_dia_limite' => (int) env('MESA_CONTABILIDADE_DIA_LIMITE', 10),

    // Por quanto tempo (segundos) o selo do menu guarda a contagem de cada usuário.
    'selo_cache_segundos' => (int) env('MESA_SELO_CACHE_SEGUNDOS', 300),
];

<?php

namespace App\Services;

use App\Models\BolsaConcedida;
use App\Models\Turma;

class ControladoriaTurmaService
{
    /**
     * Calcula as métricas detalhadas de rentabilidade e ponto de equilíbrio de uma turma.
     *
     * @return array<string, mixed>
     */
    public function calcularMetricas(Turma $turma): array
    {
        $alunosAtivos = $turma->matriculasAtivasCount();
        $vagasMaximas = (int) ($turma->vagas_maximas ?? 0);
        $taxaOcupacao = $vagasMaximas > 0 ? round(($alunosAtivos / $vagasMaximas) * 100, 1) : 0.0;

        // Mensalidade de tabela base da turma ou herdada do curso
        $mensalidadeBase = (float) ($turma->mensalidade_base ?? 0);
        if ($mensalidadeBase <= 0 && $turma->serie?->curso?->valor_anual) {
            $mensalidadeBase = round((float) $turma->serie->curso->valor_anual / 12, 2);
        }

        // Carrega matrículas ativas para cálculo real de receita e bolsas
        $matriculas = $turma->matriculas()
            ->with('contrato')
            ->where(function ($q) {
                $q->whereNull('data_ativacao')->orWhereDate('data_ativacao', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('data_desativacao')->orWhereDate('data_desativacao', '>', now());
            })
            ->get();

        $receitaBruta = 0.0;
        $totalDescontos = 0.0;

        foreach ($matriculas as $matricula) {
            $valorContrato = (float) ($matricula->contrato?->valor_total ?? 0);
            $mensalidadeAluno = $valorContrato > 0 ? round($valorContrato / 12, 2) : $mensalidadeBase;

            $receitaBruta += $mensalidadeAluno;

            // Descontos de bolsas ativas
            $pctBolsa = BolsaConcedida::percentualAtivoPara($matricula);
            if ($pctBolsa > 0) {
                $totalDescontos += round(($mensalidadeAluno * ($pctBolsa / 100)), 2);
            }
        }

        // Se não houver matrículas cadastradas mas houver mensalidade base cadastrada
        if ($alunosAtivos === 0 && $receitaBruta === 0.0) {
            $ticketMedio = $mensalidadeBase;
        } else {
            $receitaLiquidaCalculada = max(0.0, $receitaBruta - $totalDescontos);
            $ticketMedio = $alunosAtivos > 0 ? round($receitaLiquidaCalculada / $alunosAtivos, 2) : $mensalidadeBase;
        }

        $receitaLiquida = max(0.0, $receitaBruta - $totalDescontos);

        $custoDocente = (float) ($turma->custo_docente_mensal ?? 0);
        $custoOperacional = (float) ($turma->custo_operacional_rateado ?? 0);
        $custoTotal = $custoDocente + $custoOperacional;

        $resultadoMensal = round($receitaLiquida - $custoTotal, 2);
        $margemPercentual = $receitaLiquida > 0
            ? round(($resultadoMensal / $receitaLiquida) * 100, 1)
            : ($custoTotal > 0 ? -100.0 : 0.0);

        // Ponto de Equilíbrio em número de alunos
        $pontoEquilibrioAlunos = 0;
        if ($custoTotal > 0 && $ticketMedio > 0) {
            $pontoEquilibrioAlunos = (int) ceil($custoTotal / $ticketMedio);
        }

        $saldoAlunosEquilibrio = $alunosAtivos - $pontoEquilibrioAlunos;
        $metaMargem = (float) ($turma->meta_margem_lucro ?? 20.0);

        $status = match (true) {
            $resultadoMensal < 0 => 'deficitaria',
            $margemPercentual < $metaMargem => 'alerta',
            default => 'lucrativa',
        };

        return [
            'turma_id' => $turma->id,
            'turma_nome' => $turma->nome,
            'turma_codigo' => $turma->codigo,
            'serie_nome' => $turma->serie?->nome ?? '-',
            'curso_nome' => $turma->serie?->curso?->nome ?? '-',
            'turno_nome' => $turma->turno?->nome ?? '-',
            'alunos_ativos' => $alunosAtivos,
            'vagas_maximas' => $vagasMaximas,
            'taxa_ocupacao' => $taxaOcupacao,
            'mensalidade_base' => $mensalidadeBase,
            'ticket_medio' => $ticketMedio,
            'receita_bruta' => $receitaBruta,
            'descontos_bolsas' => $totalDescontos,
            'receita_liquida' => $receitaLiquida,
            'custo_docente' => $custoDocente,
            'custo_operacional' => $custoOperacional,
            'custo_total' => $custoTotal,
            'resultado_mensal' => $resultadoMensal,
            'margem_percentual' => $margemPercentual,
            'meta_margem' => $metaMargem,
            'ponto_equilibrio_alunos' => $pontoEquilibrioAlunos,
            'saldo_alunos_equilibrio' => $saldoAlunosEquilibrio,
            'status' => $status,
        ];
    }

    /**
     * Simula cenários de captação de novos alunos e reajustes na mensalidade ou custos.
     *
     * @return array<string, mixed>
     */
    public function simularCenario(
        Turma $turma,
        int $novosAlunos = 0,
        float $reajusteMensalidadePct = 0.0,
        float $variacaoCustoDocente = 0.0
    ): array {
        $atual = $this->calcularMetricas($turma);

        $simuladoAlunos = max(0, $atual['alunos_ativos'] + $novosAlunos);
        $simuladoTicket = round($atual['ticket_medio'] * (1 + ($reajusteMensalidadePct / 100)), 2);
        $simuladaReceitaLiquida = round($simuladoAlunos * $simuladoTicket, 2);

        $simuladoCustoDocente = max(0.0, $atual['custo_docente'] + $variacaoCustoDocente);
        $simuladoCustoTotal = $simuladoCustoDocente + $atual['custo_operacional'];

        $simuladoResultado = round($simuladaReceitaLiquida - $simuladoCustoTotal, 2);
        $simuladaMargem = $simuladaReceitaLiquida > 0
            ? round(($simuladoResultado / $simuladaReceitaLiquida) * 100, 1)
            : ($simuladoCustoTotal > 0 ? -100.0 : 0.0);

        $simuladoBreakEven = $simuladoTicket > 0 ? (int) ceil($simuladoCustoTotal / $simuladoTicket) : 0;
        $simuladoSaldo = $simuladoAlunos - $simuladoBreakEven;

        return [
            'atual' => $atual,
            'simulado' => [
                'novos_alunos' => $novosAlunos,
                'reajuste_mensalidade_pct' => $reajusteMensalidadePct,
                'variacao_custo_docente' => $variacaoCustoDocente,
                'alunos' => $simuladoAlunos,
                'ticket_medio' => $simuladoTicket,
                'receita_liquida' => $simuladaReceitaLiquida,
                'custo_total' => $simuladoCustoTotal,
                'resultado_mensal' => $simuladoResultado,
                'margem_percentual' => $simuladaMargem,
                'ponto_equilibrio_alunos' => $simuladoBreakEven,
                'saldo_alunos_equilibrio' => $simuladoSaldo,
                'variacao_resultado' => round($simuladoResultado - $atual['resultado_mensal'], 2),
                'status' => match (true) {
                    $simuladoResultado < 0 => 'deficitaria',
                    $simuladaMargem < $atual['meta_margem'] => 'alerta',
                    default => 'lucrativa',
                },
            ],
        ];
    }

    /**
     * Consolida os indicadores de todas as turmas filtradas.
     *
     * @return array<string, mixed>
     */
    public function calcularConsolidado(?int $periodoLetivoId = null, ?int $unidadeId = null): array
    {
        $query = Turma::query()->with(['serie.curso.unidade', 'turno']);

        if ($periodoLetivoId) {
            $query->where('periodo_letivo_id', $periodoLetivoId);
        }

        if ($unidadeId) {
            $query->whereHas('serie.curso', fn ($q) => $q->where('unidade_id', $unidadeId));
        }

        $turmas = $query->get();

        $totalTurmas = $turmas->count();
        $totalAlunos = 0;
        $totalVagas = 0;
        $totalReceitaLiquida = 0.0;
        $totalCustoTotal = 0.0;
        $lucrativasCount = 0;
        $alertaCount = 0;
        $deficitariasCount = 0;

        foreach ($turmas as $turma) {
            $metricas = $this->calcularMetricas($turma);

            $totalAlunos += $metricas['alunos_ativos'];
            $totalVagas += $metricas['vagas_maximas'];
            $totalReceitaLiquida += $metricas['receita_liquida'];
            $totalCustoTotal += $metricas['custo_total'];

            match ($metricas['status']) {
                'lucrativa' => $lucrativasCount++,
                'alerta' => $alertaCount++,
                'deficitaria' => $deficitariasCount++,
            };
        }

        $resultadoGeral = round($totalReceitaLiquida - $totalCustoTotal, 2);
        $margemGeral = $totalReceitaLiquida > 0
            ? round(($resultadoGeral / $totalReceitaLiquida) * 100, 1)
            : 0.0;

        $ocupacaoMedia = $totalVagas > 0
            ? round(($totalAlunos / $totalVagas) * 100, 1)
            : 0.0;

        return [
            'total_turmas' => $totalTurmas,
            'total_alunos' => $totalAlunos,
            'total_vagas' => $totalVagas,
            'ocupacao_media' => $ocupacaoMedia,
            'receita_liquida_total' => $totalReceitaLiquida,
            'custo_total_geral' => $totalCustoTotal,
            'resultado_geral' => $resultadoGeral,
            'margem_geral' => $margemGeral,
            'turmas_lucrativas' => $lucrativasCount,
            'turmas_alerta' => $alertaCount,
            'turmas_deficitarias' => $deficitariasCount,
        ];
    }
}

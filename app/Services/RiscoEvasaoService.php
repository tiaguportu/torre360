<?php

namespace App\Services;

use App\Enums\SituacaoFinal;
use App\Models\Matricula;
use Illuminate\Support\Facades\DB;

/**
 * Calcula o Risco de Evasão (0-100) de uma matrícula ativa, combinando frequência
 * recente, desempenho acadêmico e inadimplência financeira. Espelha o mesmo padrão
 * do LeadScoreService (CRM), mas para alunos já matriculados em vez de leads.
 * Ver docs/risco_evasao_roadmap.md para a explicação de cada fator e pesos.
 */
class RiscoEvasaoService
{
    /**
     * Quantos dias olhar para trás ao calcular o percentual de faltas recentes.
     */
    private const JANELA_FREQUENCIA_DIAS = 30;

    /**
     * Calcula o score (0-100) da matrícula a partir do estado atual no banco.
     */
    public static function calcular(Matricula $matricula): int
    {
        return collect(self::detalhar($matricula))->sum('pontos');
    }

    /**
     * Retorna o detalhamento do score por fator, para exibição na ficha da matrícula.
     *
     * @return array<int, array{fator: string, pontos: int, maximo: int}>
     */
    public static function detalhar(Matricula $matricula): array
    {
        $pesos = config('risco_evasao.pesos');

        return [
            ['fator' => 'Frequência', 'pontos' => self::pontosFrequencia($matricula), 'maximo' => $pesos['frequencia']],
            ['fator' => 'Desempenho', 'pontos' => self::pontosDesempenho($matricula), 'maximo' => $pesos['desempenho']],
            ['fator' => 'Inadimplência', 'pontos' => self::pontosInadimplencia($matricula), 'maximo' => $pesos['inadimplencia']],
        ];
    }

    /**
     * Recalcula e persiste o score da matrícula. Faz um update direto na tabela
     * (sem passar pelos eventos do Eloquent), mesmo princípio do LeadScoreService.
     */
    public static function recalcular(Matricula $matricula): int
    {
        $matricula->refresh();

        $score = self::calcular($matricula);

        DB::table('matricula')
            ->where('id', $matricula->id)
            ->update([
                'risco_evasao_score' => $score,
                'risco_evasao_atualizado_em' => now(),
            ]);

        $matricula->risco_evasao_score = $score;

        return $score;
    }

    /**
     * Cor Filament (success/warning/danger) correspondente à faixa do score. Ao
     * contrário do Lead Score, aqui pontuação alta é ruim (mais risco).
     */
    public static function cor(?int $score): string
    {
        $faixas = config('risco_evasao.faixas_cor');

        return match (true) {
            $score === null => 'gray',
            $score >= $faixas['alto'] => 'danger',
            $score >= $faixas['moderado'] => 'warning',
            default => 'success',
        };
    }

    /**
     * % de faltas nos últimos 30 dias, sobre as aulas com frequência lançada no
     * período (sem aulas registradas, não há o que avaliar: 0 pontos).
     */
    private static function pontosFrequencia(Matricula $matricula): int
    {
        $desde = now()->subDays(self::JANELA_FREQUENCIA_DIAS)->toDateString();

        $registros = $matricula->frequenciaEscolars()
            ->whereHas('cronogramaAula', fn ($q) => $q->where('data', '>=', $desde))
            ->get();

        $total = $registros->count();

        if ($total === 0) {
            return 0;
        }

        $percentualFaltas = ($registros->where('situacao', 'ausente')->count() / $total) * 100;

        foreach (config('risco_evasao.frequencia') as $faixa) {
            if ($percentualFaltas >= $faixa['minimo']) {
                return $faixa['pontos'];
            }
        }

        return 0;
    }

    /**
     * Pior situação final entre as disciplinas do período letivo mais recente que já
     * teve fechamento de ciclo registrado para esta matrícula.
     */
    private static function pontosDesempenho(Matricula $matricula): int
    {
        $situacoes = $matricula->situacoesFinais;

        if ($situacoes->isEmpty()) {
            return 0;
        }

        $ultimoPeriodoId = $situacoes->max('periodo_letivo_id');
        $doUltimoPeriodo = $situacoes->where('periodo_letivo_id', $ultimoPeriodoId);

        $tabela = config('risco_evasao.desempenho');

        if ($doUltimoPeriodo->contains('situacao', SituacaoFinal::REPROVADO)) {
            return $tabela['reprovado'];
        }

        if ($doUltimoPeriodo->contains('situacao', SituacaoFinal::RECUPERACAO)) {
            return $tabela['recuperacao'];
        }

        return $tabela['aprovado'];
    }

    /**
     * Reaproveita Matricula::hasDebitosVencidos() (Onda 7) em vez de reimplementar a
     * lógica de atraso financeiro.
     */
    private static function pontosInadimplencia(Matricula $matricula): int
    {
        return $matricula->hasDebitosVencidos() ? config('risco_evasao.pesos.inadimplencia') : 0;
    }
}

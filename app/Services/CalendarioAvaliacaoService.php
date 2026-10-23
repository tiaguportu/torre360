<?php

namespace App\Services;

use App\Models\Avaliacao;
use App\Models\DiaNaoLetivo;
use App\Models\Turma;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CalendarioAvaliacaoService
{
    public const LIMITE_AVALIACOES_DIA_PADRAO = 2;

    public const LIMITE_AVALIACOES_SEMANA_PADRAO = 4;

    /**
     * Verifica se a data prevista para a avaliação conflita com outras provas da mesma turma ou dias não letivos.
     */
    public function verificarConflitos(int $turmaId, string $dataPrevista, ?int $avaliacaoIdExcluir = null): array
    {
        $turma = Turma::with('periodoLetivo')->find($turmaId);
        if (! $turma) {
            return ['tem_conflito' => false, 'mensagens' => []];
        }

        $dt = Carbon::parse($dataPrevista);
        $dataStr = $dt->toDateString();
        $mensagens = [];

        // 1. Provas no mesmo dia para a mesma turma
        $provasDoDia = Avaliacao::with('disciplina')
            ->where('turma_id', $turmaId)
            ->whereDate('data_prevista', $dataStr)
            ->when($avaliacaoIdExcluir, fn ($q) => $q->where('id', '!=', $avaliacaoIdExcluir))
            ->get();

        $totalNoDia = $provasDoDia->count();
        $limiteDiarioExcedido = $totalNoDia >= self::LIMITE_AVALIACOES_DIA_PADRAO;

        if ($limiteDiarioExcedido) {
            $disciplinas = $provasDoDia->pluck('disciplina.nome')->filter()->implode(', ');
            $mensagens[] = "Limite diário pedagógico excedido: a turma já possui {$totalNoDia} avaliação(ões) agendada(s) para {$dt->format('d/m/Y')} ({$disciplinas}). Limite recomendado: ".self::LIMITE_AVALIACOES_DIA_PADRAO.' por dia.';
        }

        // 2. Provas na mesma semana
        $inicioSemana = $dt->copy()->startOfWeek()->toDateString();
        $fimSemana = $dt->copy()->endOfWeek()->toDateString();

        $provasNaSemana = Avaliacao::where('turma_id', $turmaId)
            ->whereDate('data_prevista', '>=', $inicioSemana)
            ->whereDate('data_prevista', '<=', $fimSemana)
            ->when($avaliacaoIdExcluir, fn ($q) => $q->where('id', '!=', $avaliacaoIdExcluir))
            ->count();

        $limiteSemanalExcedido = $provasNaSemana >= self::LIMITE_AVALIACOES_SEMANA_PADRAO;
        if ($limiteSemanalExcedido) {
            $mensagens[] = "Sobrecarga semanal: a turma acumula {$provasNaSemana} prova(s) agendada(s) nesta semana ({$dt->copy()->startOfWeek()->format('d/m')} a {$dt->copy()->endOfWeek()->format('d/m')}). Limite recomendado: ".self::LIMITE_AVALIACOES_SEMANA_PADRAO.' por semana.';
        }

        // 3. Verificação de Dia Não Letivo / Feriado / Recesso
        $diaNaoLetivo = DiaNaoLetivo::where('periodo_letivo_id', $turma->periodo_letivo_id)
            ->where('data', $dataStr)
            ->where('flag_ativo', '1')
            ->first();

        // Checagem de feriado nacional genérico
        $feriadoNacional = DiaNaoLetivo::getFeriadoNacional($dt);

        $ehDiaNaoLetivo = false;
        $motivoDiaNaoLetivo = null;

        if ($diaNaoLetivo) {
            $ehDiaNaoLetivo = true;
            $motivoDiaNaoLetivo = $diaNaoLetivo->descricao ?? 'Dia não letivo registrado';
            $mensagens[] = "Data em dia não letivo: {$dt->format('d/m/Y')} está cadastrada como '{$motivoDiaNaoLetivo}' no calendário escolar.";
        } elseif ($feriadoNacional) {
            $ehDiaNaoLetivo = true;
            $motivoDiaNaoLetivo = $feriadoNacional;
            $mensagens[] = "Data em feriado nacional: {$dt->format('d/m/Y')} é '{$feriadoNacional}'.";
        } elseif ($dt->isWeekend()) {
            $mensagens[] = "Atenção: {$dt->format('d/m/Y')} cai em um final de semana ({$dt->translatedFormat('l')}).";
        }

        return [
            'tem_conflito' => ! empty($mensagens),
            'limite_diario_excedido' => $limiteDiarioExcedido,
            'total_no_dia' => $totalNoDia,
            'limite_semanal_excedido' => $limiteSemanalExcedido,
            'total_na_semana' => $provasNaSemana,
            'eh_dia_nao_letivo' => $ehDiaNaoLetivo,
            'motivo_dia_nao_letivo' => $motivoDiaNaoLetivo,
            'mensagens' => $mensagens,
            'aviso_formatado' => implode(' | ', $mensagens),
        ];
    }

    /**
     * Retorna todas as avaliações agendadas de uma turma organizadas por data para o calendário.
     */
    public function obterGradeAvaliacoes(Turma $turma, ?string $mesAno = null): Collection
    {
        $query = Avaliacao::with(['disciplina', 'professor', 'categoria'])
            ->where('turma_id', $turma->id)
            ->orderBy('data_prevista');

        if ($mesAno) {
            [$ano, $mes] = explode('-', $mesAno);
            $query->whereYear('data_prevista', (int) $ano)->whereMonth('data_prevista', (int) $mes);
        }

        return $query->get();
    }
}

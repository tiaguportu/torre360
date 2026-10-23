<?php

namespace App\Services;

use App\Models\CronogramaAula;
use App\Models\ReposicaoAula;
use App\Models\Turma;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CargaLetivaService
{
    public const DIAS_LETIVOS_MINIMOS_LDB = 200;

    public const HORAS_MINIMAS_FUNDAMENTAL = 800;

    public const HORAS_MINIMAS_MEDIO = 1000;

    /**
     * Retorna a exigência legal de dias e horas (hora-relógio) da turma com base na LDB art. 24, I.
     */
    public function obterMinimosLegais(Turma $turma): array
    {
        $turma->loadMissing('serie.curso');
        $nomeCurso = mb_strtolower($turma->serie?->curso?->nome_externo ?? $turma->serie?->curso?->nome_interno ?? '');

        $isMedio = str_contains($nomeCurso, 'médio') || str_contains($nomeCurso, 'medio');
        $horasMinimas = $isMedio ? self::HORAS_MINIMAS_MEDIO : self::HORAS_MINIMAS_FUNDAMENTAL;

        return [
            'minimo_dias' => self::DIAS_LETIVOS_MINIMOS_LDB,
            'minimo_horas' => $horasMinimas,
        ];
    }

    /**
     * Apura o cumprimento de carga letiva e dias escolares de uma turma.
     */
    public function apurarTurma(Turma $turma): array
    {
        $minimos = $this->obterMinimosLegais($turma);
        $hoje = now()->toDateString();

        $aulas = CronogramaAula::with('frequencias')
            ->where('turma_id', $turma->id)
            ->get();

        // 1. Apuração de Dias Letivos
        $diasPrevistosCollection = $aulas->pluck('data')->map(fn ($d) => $d instanceof Carbon ? $d->toDateString() : (string) $d)->unique()->filter();
        $totalDiasPrevistos = $diasPrevistosCollection->count();

        $aulasRealizadas = $aulas->filter(function (CronogramaAula $aula) use ($hoje) {
            $dataStr = $aula->data instanceof Carbon ? $aula->data->toDateString() : (string) $aula->data;
            if ($dataStr > $hoje) {
                return false;
            }

            return $aula->frequencias->isNotEmpty() || ! empty($aula->conteudo_ministrado);
        });

        $totalDiasRealizados = $aulasRealizadas->pluck('data')->map(fn ($d) => $d instanceof Carbon ? $d->toDateString() : (string) $d)->unique()->count();

        // 2. Apuração de Horas-Relógio (conversão de minutos para horas de 60 min)
        $minutosPrevistos = 0;
        foreach ($aulas as $aula) {
            $minutosPrevistos += $this->calcularDuracaoMinutos($aula->hora_inicio, $aula->hora_fim);
        }
        $horasPrevistas = round($minutosPrevistos / 60, 2);

        $minutosRealizados = 0;
        foreach ($aulasRealizadas as $aula) {
            $minutosRealizados += $this->calcularDuracaoMinutos($aula->hora_inicio, $aula->hora_fim);
        }
        $horasRealizadas = round($minutosRealizados / 60, 2);

        // 3. Reposições de Aulas
        $reposicoes = ReposicaoAula::where('turma_id', $turma->id)->get();
        $reposicoesRealizadas = $reposicoes->where('status', 'realizada');
        $reposicoesAgendadas = $reposicoes->where('status', 'agendada');

        $minutosReposicaoRealizados = $reposicoesRealizadas->sum(fn (ReposicaoAula $r) => $r->duracaoMinutos());
        $minutosReposicaoAgendados = $reposicoesAgendadas->sum(fn (ReposicaoAula $r) => $r->duracaoMinutos());

        $horasReposicaoRealizadas = round($minutosReposicaoRealizados / 60, 2);
        $horasReposicaoAgendadas = round($minutosReposicaoAgendados / 60, 2);

        // Dias de reposição que não coincidem com dias normais de aula
        $diasReposicaoRealizados = $reposicoesRealizadas->pluck('data_reposicao')->map(fn ($d) => $d instanceof Carbon ? $d->toDateString() : (string) $d)->diff($diasPrevistosCollection)->unique()->count();
        $diasReposicaoAgendados = $reposicoesAgendadas->pluck('data_reposicao')->map(fn ($d) => $d instanceof Carbon ? $d->toDateString() : (string) $d)->diff($diasPrevistosCollection)->unique()->count();

        // Projeções Finais
        $totalDiasProjetados = $totalDiasPrevistos + $diasReposicaoRealizados + $diasReposicaoAgendados;
        $totalHorasProjetadas = round($horasPrevistas + $horasReposicaoRealizadas + $horasReposicaoAgendadas, 2);

        // Percentuais de Cumprimento em relação ao mínimo legal
        $percentualDias = $minimos['minimo_dias'] > 0 ? round(($totalDiasProjetados / $minimos['minimo_dias']) * 100, 1) : 100;
        $percentualHoras = $minimos['minimo_horas'] > 0 ? round(($totalHorasProjetadas / $minimos['minimo_horas']) * 100, 1) : 100;

        // Semáforo da Turma
        $statusSemaforo = 'em_dia';
        if ($totalDiasProjetados < $minimos['minimo_dias'] || $totalHorasProjetadas < $minimos['minimo_horas']) {
            if ($percentualDias < 90 || $percentualHoras < 90) {
                $statusSemaforo = 'insuficiente';
            } else {
                $statusSemaforo = 'em_risco';
            }
        }

        return [
            'turma_id' => $turma->id,
            'turma_nome' => $turma->nome,
            'minimo_legal_dias' => $minimos['minimo_dias'],
            'minimo_legal_horas' => $minimos['minimo_horas'],
            'dias_previstos' => $totalDiasPrevistos,
            'dias_realizados' => $totalDiasRealizados,
            'dias_projetados' => $totalDiasProjetados,
            'dias_deficit' => max(0, $minimos['minimo_dias'] - $totalDiasProjetados),
            'horas_previstas' => $horasPrevistas,
            'horas_realizadas' => $horasRealizadas,
            'horas_projetadas' => $totalHorasProjetadas,
            'horas_deficit' => max(0.0, round($minimos['minimo_horas'] - $totalHorasProjetadas, 2)),
            'total_reposicoes' => $reposicoes->count(),
            'reposicoes_agendadas' => $reposicoesAgendadas->count(),
            'reposicoes_realizadas' => $reposicoesRealizadas->count(),
            'horas_reposicao_agendadas' => $horasReposicaoAgendadas,
            'horas_reposicao_realizadas' => $horasReposicaoRealizadas,
            'percentual_dias' => $percentualDias,
            'percentual_horas' => $percentualHoras,
            'status' => $statusSemaforo, // em_dia, em_risco, insuficiente
        ];
    }

    /**
     * Agenda uma reposição de aula e cria a respectiva aula no cronograma para a turma.
     */
    public function agendarReposicao(array $dados, User $criador): ReposicaoAula
    {
        return DB::transaction(function () use ($dados, $criador) {
            // Cria a aula no cronograma para constar na grade escolar
            $aulaCronograma = CronogramaAula::create([
                'turma_id' => $dados['turma_id'],
                'disciplina_id' => $dados['disciplina_id'],
                'pessoa_id' => $dados['pessoa_id'] ?? null,
                'data' => $dados['data_reposicao'],
                'hora_inicio' => $dados['hora_inicio'],
                'hora_fim' => $dados['hora_fim'],
                'conteudo_ministrado' => 'Aula de Reposição: '.($dados['motivo'] ?? 'Compensação de carga horária'),
            ]);

            return ReposicaoAula::create([
                'turma_id' => $dados['turma_id'],
                'disciplina_id' => $dados['disciplina_id'],
                'pessoa_id' => $dados['pessoa_id'] ?? null,
                'cronograma_aula_original_id' => $dados['cronograma_aula_original_id'] ?? null,
                'cronograma_aula_reposicao_id' => $aulaCronograma->id,
                'data_reposicao' => $dados['data_reposicao'],
                'hora_inicio' => $dados['hora_inicio'],
                'hora_fim' => $dados['hora_fim'],
                'motivo' => $dados['motivo'],
                'status' => 'agendada',
                'observacoes' => $dados['observacoes'] ?? null,
                'criado_por_user_id' => $criador->id,
            ]);
        });
    }

    /**
     * Conclui uma reposição de aula.
     */
    public function concluirReposicao(ReposicaoAula $reposicao): void
    {
        $reposicao->update(['status' => 'realizada']);
    }

    private function calcularDuracaoMinutos(?string $inicio, ?string $fim): int
    {
        if (! $inicio || ! $fim) {
            return 50; // Duração padrão da hora-aula caso não haja horário
        }

        try {
            $dtInicio = Carbon::parse($inicio);
            $dtFim = Carbon::parse($fim);

            return abs($dtFim->diffInMinutes($dtInicio));
        } catch (\Throwable) {
            return 50;
        }
    }
}

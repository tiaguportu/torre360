<?php

namespace App\Services;

use App\Models\ApuracaoHoraAula;
use App\Models\CronogramaAula;
use App\Models\Pessoa;
use App\Models\ReposicaoAula;
use App\Models\User;
use Carbon\Carbon;

class ApuracaoHoraAulaService
{
    public const PERCENTUAL_DSR_PADRAO = 16.67; // 1/6 da CLT art. 320

    public const VALOR_HORA_AULA_PADRAO = 45.00; // Valor padrão de referência por hora-aula

    /**
     * Apura as horas-aulas de um docente em uma competência (ano/mês).
     */
    public function apurarProfessor(
        Pessoa $professor,
        int $ano,
        int $mes,
        ?float $valorHoraAula = null,
        float $horasAtividade = 0.0,
        ?User $criador = null
    ): ApuracaoHoraAula {
        $dataInicio = Carbon::createFromDate($ano, $mes, 1)->startOfMonth()->toDateString();
        $dataFim = Carbon::createFromDate($ano, $mes, 1)->endOfMonth()->toDateString();

        // 1. Busca todas as aulas programadas no cronograma do professor no mês
        $aulas = CronogramaAula::with('frequencias')
            ->where('pessoa_id', $professor->id)
            ->whereBetween('data', [$dataInicio, $dataFim])
            ->get();

        $totalPrevistas = $aulas->count();

        // Aula ministrada: frequência lançada ou conteúdo registrado
        $aulasMinistradas = $aulas->filter(function (CronogramaAula $aula) {
            return $aula->frequencias->isNotEmpty() || ! empty($aula->conteudo_ministrado);
        })->count();

        $totalFaltadas = max(0, $totalPrevistas - $aulasMinistradas);

        // 2. Busca reposições realizadas pelo professor no mês (Onda 49)
        $totalReposicoes = ReposicaoAula::where('pessoa_id', $professor->id)
            ->where('status', 'realizada')
            ->whereBetween('data_reposicao', [$dataInicio, $dataFim])
            ->count();

        // 3. Cálculo Financeiro
        $valorHora = $valorHoraAula ?? self::VALOR_HORA_AULA_PADRAO;
        $totalAulasPagas = $aulasMinistradas + $totalReposicoes + $horasAtividade;
        $baseRemuneracao = round($totalAulasPagas * $valorHora, 2);
        $dsr = round($baseRemuneracao * (self::PERCENTUAL_DSR_PADRAO / 100), 2);
        $totalBruto = round($baseRemuneracao + $dsr, 2);

        // 4. Salva ou atualiza a apuração mensal
        return ApuracaoHoraAula::updateOrCreate(
            [
                'pessoa_id' => $professor->id,
                'ano' => $ano,
                'mes' => $mes,
            ],
            [
                'total_aulas_previstas' => $totalPrevistas,
                'total_aulas_ministradas' => $aulasMinistradas,
                'total_aulas_reposicao' => $totalReposicoes,
                'total_aulas_faltadas' => $totalFaltadas,
                'total_horas_atividade' => $horasAtividade,
                'valor_hora_aula' => $valorHora,
                'percentual_dsr' => self::PERCENTUAL_DSR_PADRAO,
                'valor_total_bruto' => $totalBruto,
                'status' => 'aberto',
                'criado_por_user_id' => $criador?->id,
            ]
        );
    }

    /**
     * Homologa o fechamento da apuração (trava para entrega contábil).
     */
    public function homologarApuracao(ApuracaoHoraAula $apuracao): void
    {
        $apuracao->update([
            'status' => 'homologado',
            'conferido_em' => now(),
        ]);
    }

    /**
     * Exporta os registros apurados de uma competência em formato CSV para a contabilidade.
     */
    public function exportarCsv(int $ano, int $mes): string
    {
        $registros = ApuracaoHoraAula::with('professor')
            ->where('ano', $ano)
            ->where('mes', $mes)
            ->get();

        $linhas = [];
        $linhas[] = 'Competencia;CPF;Professor;AulasPrevistas;AulasMinistradas;Reposicoes;Faltas;HorasAtividade;ValorHoraAula;ValorBruto;Status';

        foreach ($registros as $reg) {
            $cpf = $reg->professor?->cpf ?? '';
            $nome = str_replace(';', ' ', $reg->professor?->nome ?? '');
            $comp = sprintf('%02d/%d', $reg->mes, $reg->ano);

            $linhas[] = implode(';', [
                $comp,
                $cpf,
                $nome,
                $reg->total_aulas_previstas,
                $reg->total_aulas_ministradas,
                $reg->total_aulas_reposicao,
                $reg->total_aulas_faltadas,
                number_format((float) $reg->total_horas_atividade, 2, ',', '.'),
                number_format((float) $reg->valor_hora_aula, 2, ',', '.'),
                number_format((float) $reg->valor_total_bruto, 2, ',', '.'),
                $reg->status,
            ]);
        }

        return implode("\r\n", $linhas);
    }
}

<?php

namespace App\Services;

use App\Models\CronogramaAula;
use App\Models\Disciplina;
use App\Models\FrequenciaEscolar;
use App\Models\Matricula;

/**
 * Resumo de frequência de um aluno para o Portal da Família.
 * Considera apenas registros com situação definida (presente/ausente).
 */
class FrequenciaAlunoService
{
    /**
     * Frequência mínima exigida (%), a mesma informada no boletim.
     */
    public const FREQUENCIA_MINIMA = 75.0;

    /**
     * @return array{total: int, presencas: int, faltas: int, percentual: ?float, abaixo_minimo: bool, por_disciplina: list<array{disciplina_id: int, nome: string, total: int, presencas: int, faltas: int, percentual: ?float, abaixo_minimo: bool}>}
     */
    public function resumo(Matricula $matricula): array
    {
        $frequencias = (new FrequenciaEscolar)->getTable();
        $aulas = (new CronogramaAula)->getTable();

        $linhas = FrequenciaEscolar::query()
            ->join($aulas, "{$aulas}.id", '=', "{$frequencias}.cronograma_aula_id")
            ->where("{$frequencias}.matricula_id", $matricula->id)
            ->whereIn("{$frequencias}.situacao", ['presente', 'ausente'])
            ->selectRaw("{$aulas}.disciplina_id as disciplina_id")
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(case when {$frequencias}.situacao = 'presente' then 1 else 0 end) as presencas")
            ->groupBy("{$aulas}.disciplina_id")
            ->get();

        $nomes = Disciplina::query()
            ->whereIn('id', $linhas->pluck('disciplina_id'))
            ->pluck('nome', 'id');

        $porDisciplina = $linhas
            ->map(fn ($linha): array => $this->montarLinha(
                (int) $linha->disciplina_id,
                (string) ($nomes[$linha->disciplina_id] ?? 'Disciplina'),
                (int) $linha->total,
                (int) $linha->presencas,
            ))
            ->sortBy('nome', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $total = (int) $porDisciplina->sum('total');
        $presencas = (int) $porDisciplina->sum('presencas');
        $percentual = $this->percentual($presencas, $total);

        return [
            'total' => $total,
            'presencas' => $presencas,
            'faltas' => $total - $presencas,
            'percentual' => $percentual,
            'abaixo_minimo' => $percentual !== null && $percentual < self::FREQUENCIA_MINIMA,
            'por_disciplina' => $porDisciplina->all(),
        ];
    }

    /**
     * @return array{disciplina_id: int, nome: string, total: int, presencas: int, faltas: int, percentual: ?float, abaixo_minimo: bool}
     */
    private function montarLinha(int $disciplinaId, string $nome, int $total, int $presencas): array
    {
        $percentual = $this->percentual($presencas, $total);

        return [
            'disciplina_id' => $disciplinaId,
            'nome' => $nome,
            'total' => $total,
            'presencas' => $presencas,
            'faltas' => $total - $presencas,
            'percentual' => $percentual,
            'abaixo_minimo' => $percentual !== null && $percentual < self::FREQUENCIA_MINIMA,
        ];
    }

    private function percentual(int $presencas, int $total): ?float
    {
        return $total > 0 ? round($presencas / $total * 100, 1) : null;
    }
}

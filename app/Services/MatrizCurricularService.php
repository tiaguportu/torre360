<?php

namespace App\Services;

use App\Models\MatrizCurricular;
use App\Models\Turma;

/**
 * Popula a grade de disciplinas de uma turma (`turma_disciplina`) a partir da
 * matriz curricular da sua série. Só adiciona o que falta — nunca remove ou
 * sobrescreve disciplinas já vinculadas manualmente.
 */
class MatrizCurricularService
{
    /**
     * @return int Quantidade de disciplinas adicionadas à turma.
     */
    public function sincronizarTurmaDisciplinas(Turma $turma): int
    {
        if (! $turma->serie_id) {
            return 0;
        }

        $disciplinaIdsDaMatriz = MatrizCurricular::query()
            ->where('serie_id', $turma->serie_id)
            ->pluck('disciplina_id');

        if ($disciplinaIdsDaMatriz->isEmpty()) {
            return 0;
        }

        $jaVinculadas = $turma->disciplinas()->pluck('disciplina.id');
        $faltantes = $disciplinaIdsDaMatriz->diff($jaVinculadas);

        if ($faltantes->isEmpty()) {
            return 0;
        }

        $turma->disciplinas()->attach($faltantes->all());

        return $faltantes->count();
    }
}

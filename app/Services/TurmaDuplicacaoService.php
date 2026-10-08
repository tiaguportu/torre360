<?php

namespace App\Services;

use App\Enums\StatusTurma;
use App\Models\PeriodoLetivo;
use App\Models\Turma;
use Illuminate\Support\Facades\DB;

class TurmaDuplicacaoService
{
    /**
     * Cria, no período de destino, uma cópia da turma (mesma série, turno, vagas, professor
     * conselheiro, etc.) para que a secretaria possa preparar o próximo ano letivo antes da
     * rematrícula.
     *
     * Copia também a estrutura da turma: disciplinas (com professor), habilidades (com professor),
     * tipos de documento exigidos e horários de funcionamento. NÃO copia matrículas, grade
     * horária, cronograma, avaliações nem planos de aula (são do ano anterior).
     *
     * Retorna `null` quando o período de destino já tem uma turma equivalente (mesma série, turno
     * e nome) ou quando o destino é o próprio período da turma — assim rodar a ação duas vezes
     * não duplica nada.
     */
    public function duplicar(Turma $origem, PeriodoLetivo $destino, StatusTurma $status = StatusTurma::Planejada): ?Turma
    {
        if ((int) $origem->periodo_letivo_id === (int) $destino->id) {
            return null;
        }

        $jaExiste = Turma::query()
            ->where('periodo_letivo_id', $destino->id)
            ->where('serie_id', $origem->serie_id)
            ->where('turno_id', $origem->turno_id)
            ->where('nome', $origem->nome)
            ->exists();

        if ($jaExiste) {
            return null;
        }

        return DB::transaction(function () use ($origem, $destino, $status) {
            // Recarrega do banco: a turma vinda de uma listagem pode trazer atributos calculados
            // (ex.: `alunos_ativos_count`) que `replicate()` tentaria gravar como coluna.
            $nova = Turma::query()->findOrFail($origem->getKey())->replicate();
            $nova->periodo_letivo_id = $destino->id;
            $nova->status = $status;
            $nova->save();

            // Consultas explícitas (não relações carregadas): o projeto proíbe lazy loading.
            $disciplinas = $origem->disciplinas()->get();
            if ($disciplinas->isNotEmpty()) {
                $nova->disciplinas()->attach(
                    $disciplinas->mapWithKeys(fn ($disciplina) => [
                        $disciplina->id => ['professor_id' => $disciplina->pivot->professor_id],
                    ])->all()
                );
            }

            $habilidades = $origem->habilidades()->get();
            if ($habilidades->isNotEmpty()) {
                $nova->habilidades()->attach(
                    $habilidades->mapWithKeys(fn ($habilidade) => [
                        $habilidade->id => ['professor_id' => $habilidade->pivot->professor_id],
                    ])->all()
                );
            }

            $tiposDocumentos = $origem->tiposDocumentos()->pluck('tipo_documento.id');
            if ($tiposDocumentos->isNotEmpty()) {
                $nova->tiposDocumentos()->attach($tiposDocumentos->all());
            }

            foreach ($origem->horariosFuncionamento()->get() as $horario) {
                $nova->horariosFuncionamento()->create($horario->only(['dia_semana', 'hora_inicio', 'hora_fim']));
            }

            return $nova;
        });
    }
}

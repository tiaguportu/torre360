<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Matricula;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Recorte "só o que eu leciono" para quem atua como professor: alunos das turmas em que a pessoa é conselheira ou
 * tem disciplina vinculada (`turma_disciplina`). É o mesmo critério usado em `PlanoAulaResource`, reunido aqui para
 * as telas novas que dependem do aluno (portfólio, pareceres) não repetirem a regra.
 *
 * Quem não atua como professor (coordenação, secretaria, administração) não é recortado.
 */
final class EscopoProfessor
{
    /**
     * @return list<int>
     */
    public static function pessoaIds(User $user): array
    {
        return array_values(array_unique(array_filter(array_merge(
            [$user->pessoa?->id],
            $user->pessoas->pluck('id')->all(),
        ))));
    }

    /**
     * @param  Builder<Matricula>  $matriculas
     * @return Builder<Matricula>
     */
    public static function restringirMatriculas(Builder $matriculas, ?User $user): Builder
    {
        if (! $user) {
            return $matriculas->whereRaw('1 = 0');
        }

        if (! $user->atuaComoProfessor()) {
            return $matriculas;
        }

        $pessoaIds = self::pessoaIds($user);

        return $matriculas->whereHas('turma', fn (Builder $turma) => $turma
            ->whereIn('professor_conselheiro_id', $pessoaIds)
            ->orWhereHas('disciplinas', fn (Builder $disciplina) => $disciplina->whereIn('turma_disciplina.professor_id', $pessoaIds)));
    }

    public static function podeVerMatricula(?User $user, ?Matricula $matricula): bool
    {
        if (! $user || ! $matricula) {
            return false;
        }

        return self::restringirMatriculas(Matricula::query()->whereKey($matricula->getKey()), $user)->exists();
    }
}

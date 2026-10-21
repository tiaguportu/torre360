<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Matricula;
use App\Models\User;

/**
 * Opções de "Aluno — Turma" para os seletores do painel, com busca por nome e já recortadas pelo escopo do professor
 * (quem atua como professor só enxerga os alunos das próprias turmas). Busca limitada: nunca carrega a escola toda.
 */
final class OpcoesMatricula
{
    /**
     * @return array<int, string>
     */
    public static function buscar(string $busca, ?User $user, int $limite = 50): array
    {
        $termo = '%'.addcslashes(trim($busca), '%_\\').'%';

        return EscopoProfessor::restringirMatriculas(
            Matricula::query()->where('situacao', 'ativa')->with(['pessoa', 'turma']),
            $user,
        )
            ->whereHas('pessoa', fn ($pessoa) => $pessoa->where('nome', 'like', $termo))
            ->limit($limite)
            ->get()
            ->sortBy(fn (Matricula $m): string => (string) $m->pessoa?->nome)
            ->mapWithKeys(fn (Matricula $m): array => [$m->id => self::rotulo($m)])
            ->all();
    }

    public static function rotulo(Matricula $matricula): string
    {
        return trim(($matricula->pessoa?->nome ?? 'Aluno').' — '.($matricula->turma?->nome ?? 'Sem turma'));
    }

    public static function rotuloPorId(int|string|null $id): ?string
    {
        $matricula = $id ? Matricula::query()->with(['pessoa', 'turma'])->find($id) : null;

        return $matricula ? self::rotulo($matricula) : null;
    }
}

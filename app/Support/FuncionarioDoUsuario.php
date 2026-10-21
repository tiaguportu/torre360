<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Funcionario;
use App\Models\User;

/**
 * Liga o usuário logado ao seu cadastro de funcionário ativo (pelas pessoas vinculadas ao usuário).
 */
final class FuncionarioDoUsuario
{
    public static function para(?User $user): ?Funcionario
    {
        if (! $user) {
            return null;
        }

        return Funcionario::query()
            ->with(['pessoa', 'unidade'])
            ->whereIn('pessoa_id', EscopoProfessor::pessoaIds($user))
            ->whereNull('data_desligamento')
            ->orderByDesc('data_admissao')
            ->first();
    }

    /**
     * O funcionário é o próprio usuário (ativo ou não: quem já saiu ainda pode consultar o próprio histórico).
     */
    public static function ehOProprio(?User $user, Funcionario $funcionario): bool
    {
        return $user !== null && in_array($funcionario->pessoa_id, EscopoProfessor::pessoaIds($user), true);
    }
}

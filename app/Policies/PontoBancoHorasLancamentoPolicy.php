<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Funcionario;
use App\Models\User;
use App\Support\FuncionarioDoUsuario;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Banco de horas: cada trabalhador vê o próprio; a administração vê todos, compensa e ajusta. Lançamento é só inserção,
 * então não existem habilidades de alterar ou apagar.
 */
class PontoBancoHorasLancamentoPolicy
{
    use HandlesAuthorization;

    public function verTodos(AuthUser $authUser): bool
    {
        return $authUser->can('VerTodos:PontoBancoHorasLancamento');
    }

    public function ver(AuthUser $authUser, Funcionario $funcionario): bool
    {
        /** @var User $authUser */
        return FuncionarioDoUsuario::ehOProprio($authUser, $funcionario) || $this->verTodos($authUser);
    }

    public function compensar(AuthUser $authUser): bool
    {
        return $authUser->can('Compensar:PontoBancoHorasLancamento');
    }

    public function ajustar(AuthUser $authUser): bool
    {
        return $authUser->can('Ajustar:PontoBancoHorasLancamento');
    }
}

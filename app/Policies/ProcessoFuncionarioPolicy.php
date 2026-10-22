<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProcessoFuncionario;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * RH é da administração. Os itens e o cancelamento só mudam enquanto o processo está em andamento; concluir tem permissão
 * própria porque o desligamento tira os acessos da pessoa ao sistema. O super admin passa por qualquer permissão
 * (Gate::before), então o estado do processo precisa estar nas habilidades. Não há exclusão: o histórico fica.
 */
class ProcessoFuncionarioPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ProcessoFuncionario');
    }

    public function view(AuthUser $authUser, ProcessoFuncionario $processo): bool
    {
        return $authUser->can('View:ProcessoFuncionario');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ProcessoFuncionario');
    }

    public function update(AuthUser $authUser, ProcessoFuncionario $processo): bool
    {
        return $authUser->can('Update:ProcessoFuncionario') && $processo->estaEmAndamento();
    }

    public function concluir(AuthUser $authUser, ProcessoFuncionario $processo): bool
    {
        return $authUser->can('Concluir:ProcessoFuncionario') && $processo->estaEmAndamento();
    }

    public function delete(AuthUser $authUser, ProcessoFuncionario $processo): bool
    {
        return false;
    }
}

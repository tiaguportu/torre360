<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AtestadoAluno;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * A secretaria registra, corrige e cancela; a coordenação consulta. Sem exclusão: o atestado tem histórico e mexe nas
 * justificativas de falta. `update` e `cancelar` carregam o estado do atestado, mas o `Gate::before` dá `true` ao super
 * admin mesmo em método de policy: por isso a tela confere `estaVigente()` por conta própria e o serviço recusa
 * corrigir ou cancelar um atestado já cancelado.
 */
class AtestadoAlunoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AtestadoAluno');
    }

    public function view(AuthUser $authUser, AtestadoAluno $atestado): bool
    {
        return $authUser->can('View:AtestadoAluno');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AtestadoAluno');
    }

    public function update(AuthUser $authUser, AtestadoAluno $atestado): bool
    {
        return $authUser->can('Update:AtestadoAluno') && $atestado->estaVigente();
    }

    public function cancelar(AuthUser $authUser, AtestadoAluno $atestado): bool
    {
        return $authUser->can('Cancelar:AtestadoAluno') && $atestado->estaVigente();
    }

    public function delete(AuthUser $authUser, AtestadoAluno $atestado): bool
    {
        return false;
    }
}

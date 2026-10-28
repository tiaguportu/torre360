<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ReposicaoAula;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Reposição de aulas (Onda 49). Sem esta política o Filament libera o recurso a qualquer conta ativa, inclusive a da família, que
 * entra no painel admin; com ela vale a permissão.
 */
class ReposicaoAulaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ReposicaoAula');
    }

    public function view(AuthUser $authUser, ReposicaoAula $reposicao): bool
    {
        return $authUser->can('View:ReposicaoAula');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ReposicaoAula');
    }

    public function update(AuthUser $authUser, ReposicaoAula $reposicao): bool
    {
        return $authUser->can('Update:ReposicaoAula');
    }

    public function delete(AuthUser $authUser, ReposicaoAula $reposicao): bool
    {
        return $authUser->can('Delete:ReposicaoAula');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ReposicaoAula');
    }
}

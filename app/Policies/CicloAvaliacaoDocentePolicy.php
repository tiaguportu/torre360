<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CicloAvaliacaoDocente;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CicloAvaliacaoDocentePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CicloAvaliacaoDocente');
    }

    public function view(AuthUser $authUser, CicloAvaliacaoDocente $ciclo): bool
    {
        return $authUser->can('View:CicloAvaliacaoDocente');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CicloAvaliacaoDocente');
    }

    public function update(AuthUser $authUser, CicloAvaliacaoDocente $ciclo): bool
    {
        return $authUser->can('Update:CicloAvaliacaoDocente');
    }

    /**
     * Ciclo com avaliações não é apagado: o histórico dos professores depende dele (encerre o ciclo em vez disso).
     */
    public function delete(AuthUser $authUser, CicloAvaliacaoDocente $ciclo): bool
    {
        return $authUser->can('Delete:CicloAvaliacaoDocente') && $ciclo->avaliacoes()->doesntExist();
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CicloAvaliacaoDocente');
    }
}

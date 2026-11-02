<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DenunciaComite;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Quem monta o comitê da ouvidoria (Onda 35): uma permissão só para todas as ações.
 */
class DenunciaComitePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('Gerenciar:DenunciaComite');
    }

    public function view(AuthUser $authUser, DenunciaComite $membro): bool
    {
        return $authUser->can('Gerenciar:DenunciaComite');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Gerenciar:DenunciaComite');
    }

    public function update(AuthUser $authUser, DenunciaComite $membro): bool
    {
        return $authUser->can('Gerenciar:DenunciaComite');
    }

    public function delete(AuthUser $authUser, DenunciaComite $membro): bool
    {
        return $authUser->can('Gerenciar:DenunciaComite');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('Gerenciar:DenunciaComite');
    }
}

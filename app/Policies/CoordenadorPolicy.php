<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Coordenador;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CoordenadorPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Coordenador');
    }

    public function view(AuthUser $authUser, Coordenador $coordenador): bool
    {
        return $authUser->can('View:Coordenador');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Coordenador');
    }

    public function update(AuthUser $authUser, Coordenador $coordenador): bool
    {
        return $authUser->can('Update:Coordenador');
    }

    public function delete(AuthUser $authUser, Coordenador $coordenador): bool
    {
        return $authUser->can('Delete:Coordenador');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Coordenador');
    }

    public function restore(AuthUser $authUser, Coordenador $coordenador): bool
    {
        return $authUser->can('Restore:Coordenador');
    }

    public function forceDelete(AuthUser $authUser, Coordenador $coordenador): bool
    {
        return $authUser->can('ForceDelete:Coordenador');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Coordenador');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Coordenador');
    }

    public function replicate(AuthUser $authUser, Coordenador $coordenador): bool
    {
        return $authUser->can('Replicate:Coordenador');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Coordenador');
    }
}

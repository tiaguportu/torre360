<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Perfil;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PerfilPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Perfil');
    }

    public function view(AuthUser $authUser, Perfil $perfil): bool
    {
        return $authUser->can('View:Perfil');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Perfil');
    }

    public function update(AuthUser $authUser, Perfil $perfil): bool
    {
        return $authUser->can('Update:Perfil');
    }

    public function delete(AuthUser $authUser, Perfil $perfil): bool
    {
        return $authUser->can('Delete:Perfil');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Perfil');
    }

    public function restore(AuthUser $authUser, Perfil $perfil): bool
    {
        return $authUser->can('Restore:Perfil');
    }

    public function forceDelete(AuthUser $authUser, Perfil $perfil): bool
    {
        return $authUser->can('ForceDelete:Perfil');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Perfil');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Perfil');
    }

    public function replicate(AuthUser $authUser, Perfil $perfil): bool
    {
        return $authUser->can('Replicate:Perfil');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Perfil');
    }
}

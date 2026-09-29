<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PlanoAula;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PlanoAulaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PlanoAula');
    }

    public function view(AuthUser $authUser, PlanoAula $planoAula): bool
    {
        return $authUser->can('View:PlanoAula');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PlanoAula');
    }

    public function update(AuthUser $authUser, PlanoAula $planoAula): bool
    {
        return $authUser->can('Update:PlanoAula');
    }

    public function delete(AuthUser $authUser, PlanoAula $planoAula): bool
    {
        return $authUser->can('Delete:PlanoAula');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:PlanoAula');
    }

    public function restore(AuthUser $authUser, PlanoAula $planoAula): bool
    {
        return $authUser->can('Restore:PlanoAula');
    }

    public function forceDelete(AuthUser $authUser, PlanoAula $planoAula): bool
    {
        return $authUser->can('ForceDelete:PlanoAula');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PlanoAula');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PlanoAula');
    }

    public function replicate(AuthUser $authUser, PlanoAula $planoAula): bool
    {
        return $authUser->can('Replicate:PlanoAula');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PlanoAula');
    }
}

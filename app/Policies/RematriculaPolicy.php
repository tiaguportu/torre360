<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Rematricula;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class RematriculaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Rematricula');
    }

    public function view(AuthUser $authUser, Rematricula $rematricula): bool
    {
        return $authUser->can('View:Rematricula');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Rematricula');
    }

    public function update(AuthUser $authUser, Rematricula $rematricula): bool
    {
        return $authUser->can('Update:Rematricula');
    }

    public function delete(AuthUser $authUser, Rematricula $rematricula): bool
    {
        return $authUser->can('Delete:Rematricula');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Rematricula');
    }

    public function restore(AuthUser $authUser, Rematricula $rematricula): bool
    {
        return $authUser->can('Restore:Rematricula');
    }

    public function forceDelete(AuthUser $authUser, Rematricula $rematricula): bool
    {
        return $authUser->can('ForceDelete:Rematricula');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Rematricula');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Rematricula');
    }

    public function replicate(AuthUser $authUser, Rematricula $rematricula): bool
    {
        return $authUser->can('Replicate:Rematricula');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Rematricula');
    }
}

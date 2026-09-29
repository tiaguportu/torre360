<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Sala;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class SalaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Sala');
    }

    public function view(AuthUser $authUser, Sala $sala): bool
    {
        return $authUser->can('View:Sala');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Sala');
    }

    public function update(AuthUser $authUser, Sala $sala): bool
    {
        return $authUser->can('Update:Sala');
    }

    public function delete(AuthUser $authUser, Sala $sala): bool
    {
        return $authUser->can('Delete:Sala');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Sala');
    }

    public function restore(AuthUser $authUser, Sala $sala): bool
    {
        return $authUser->can('Restore:Sala');
    }

    public function forceDelete(AuthUser $authUser, Sala $sala): bool
    {
        return $authUser->can('ForceDelete:Sala');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Sala');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Sala');
    }

    public function replicate(AuthUser $authUser, Sala $sala): bool
    {
        return $authUser->can('Replicate:Sala');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Sala');
    }
}

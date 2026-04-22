<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Nota;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class NotaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Nota');
    }

    public function view(AuthUser $authUser, Nota $nota): bool
    {
        return $authUser->can('View:Nota');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Nota');
    }

    public function update(AuthUser $authUser, Nota $nota): bool
    {
        return $authUser->can('Update:Nota');
    }

    public function delete(AuthUser $authUser, Nota $nota): bool
    {
        return $authUser->can('Delete:Nota');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Nota');
    }

    public function restore(AuthUser $authUser, Nota $nota): bool
    {
        return $authUser->can('Restore:Nota');
    }

    public function forceDelete(AuthUser $authUser, Nota $nota): bool
    {
        return $authUser->can('ForceDelete:Nota');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Nota');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Nota');
    }

    public function replicate(AuthUser $authUser, Nota $nota): bool
    {
        return $authUser->can('Replicate:Nota');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Nota');
    }
}

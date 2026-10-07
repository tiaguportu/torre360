<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Concorrente;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ConcorrentePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Concorrente');
    }

    public function view(AuthUser $authUser, Concorrente $concorrente): bool
    {
        return $authUser->can('View:Concorrente');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Concorrente');
    }

    public function update(AuthUser $authUser, Concorrente $concorrente): bool
    {
        return $authUser->can('Update:Concorrente');
    }

    public function delete(AuthUser $authUser, Concorrente $concorrente): bool
    {
        return $authUser->can('Delete:Concorrente');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Concorrente');
    }

    public function restore(AuthUser $authUser, Concorrente $concorrente): bool
    {
        return $authUser->can('Restore:Concorrente');
    }

    public function forceDelete(AuthUser $authUser, Concorrente $concorrente): bool
    {
        return $authUser->can('ForceDelete:Concorrente');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Concorrente');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Concorrente');
    }

    public function replicate(AuthUser $authUser, Concorrente $concorrente): bool
    {
        return $authUser->can('Replicate:Concorrente');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Concorrente');
    }
}

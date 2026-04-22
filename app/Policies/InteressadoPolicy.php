<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Interessado;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class InteressadoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Interessado');
    }

    public function view(AuthUser $authUser, Interessado $interessado): bool
    {
        return $authUser->can('View:Interessado');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Interessado');
    }

    public function update(AuthUser $authUser, Interessado $interessado): bool
    {
        return $authUser->can('Update:Interessado');
    }

    public function delete(AuthUser $authUser, Interessado $interessado): bool
    {
        return $authUser->can('Delete:Interessado');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Interessado');
    }

    public function restore(AuthUser $authUser, Interessado $interessado): bool
    {
        return $authUser->can('Restore:Interessado');
    }

    public function forceDelete(AuthUser $authUser, Interessado $interessado): bool
    {
        return $authUser->can('ForceDelete:Interessado');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Interessado');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Interessado');
    }

    public function replicate(AuthUser $authUser, Interessado $interessado): bool
    {
        return $authUser->can('Replicate:Interessado');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Interessado');
    }
}

<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Unidade;
use Illuminate\Auth\Access\HandlesAuthorization;

class UnidadePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Unidade');
    }

    public function view(AuthUser $authUser, Unidade $unidade): bool
    {
        return $authUser->can('View:Unidade');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Unidade');
    }

    public function update(AuthUser $authUser, Unidade $unidade): bool
    {
        return $authUser->can('Update:Unidade');
    }

    public function delete(AuthUser $authUser, Unidade $unidade): bool
    {
        return $authUser->can('Delete:Unidade');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Unidade');
    }

    public function restore(AuthUser $authUser, Unidade $unidade): bool
    {
        return $authUser->can('Restore:Unidade');
    }

    public function forceDelete(AuthUser $authUser, Unidade $unidade): bool
    {
        return $authUser->can('ForceDelete:Unidade');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Unidade');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Unidade');
    }

    public function replicate(AuthUser $authUser, Unidade $unidade): bool
    {
        return $authUser->can('Replicate:Unidade');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Unidade');
    }

}
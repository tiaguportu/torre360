<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Pais;
use Illuminate\Auth\Access\HandlesAuthorization;

class PaisPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Pais');
    }

    public function view(AuthUser $authUser, Pais $pais): bool
    {
        return $authUser->can('View:Pais');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Pais');
    }

    public function update(AuthUser $authUser, Pais $pais): bool
    {
        return $authUser->can('Update:Pais');
    }

    public function delete(AuthUser $authUser, Pais $pais): bool
    {
        return $authUser->can('Delete:Pais');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Pais');
    }

    public function restore(AuthUser $authUser, Pais $pais): bool
    {
        return $authUser->can('Restore:Pais');
    }

    public function forceDelete(AuthUser $authUser, Pais $pais): bool
    {
        return $authUser->can('ForceDelete:Pais');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Pais');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Pais');
    }

    public function replicate(AuthUser $authUser, Pais $pais): bool
    {
        return $authUser->can('Replicate:Pais');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Pais');
    }

}
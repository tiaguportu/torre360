<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Sexo;
use Illuminate\Auth\Access\HandlesAuthorization;

class SexoPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Sexo');
    }

    public function view(AuthUser $authUser, Sexo $sexo): bool
    {
        return $authUser->can('View:Sexo');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Sexo');
    }

    public function update(AuthUser $authUser, Sexo $sexo): bool
    {
        return $authUser->can('Update:Sexo');
    }

    public function delete(AuthUser $authUser, Sexo $sexo): bool
    {
        return $authUser->can('Delete:Sexo');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Sexo');
    }

    public function restore(AuthUser $authUser, Sexo $sexo): bool
    {
        return $authUser->can('Restore:Sexo');
    }

    public function forceDelete(AuthUser $authUser, Sexo $sexo): bool
    {
        return $authUser->can('ForceDelete:Sexo');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Sexo');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Sexo');
    }

    public function replicate(AuthUser $authUser, Sexo $sexo): bool
    {
        return $authUser->can('Replicate:Sexo');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Sexo');
    }

}
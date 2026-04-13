<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Cidade;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CidadePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Cidade');
    }

    public function view(AuthUser $authUser, Cidade $cidade): bool
    {
        return $authUser->can('View:Cidade');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Cidade');
    }

    public function update(AuthUser $authUser, Cidade $cidade): bool
    {
        return $authUser->can('Update:Cidade');
    }

    public function delete(AuthUser $authUser, Cidade $cidade): bool
    {
        return $authUser->can('Delete:Cidade');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Cidade');
    }

    public function restore(AuthUser $authUser, Cidade $cidade): bool
    {
        return $authUser->can('Restore:Cidade');
    }

    public function forceDelete(AuthUser $authUser, Cidade $cidade): bool
    {
        return $authUser->can('ForceDelete:Cidade');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Cidade');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Cidade');
    }

    public function replicate(AuthUser $authUser, Cidade $cidade): bool
    {
        return $authUser->can('Replicate:Cidade');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Cidade');
    }
}

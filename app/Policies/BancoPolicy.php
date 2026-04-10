<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Banco;
use Illuminate\Auth\Access\HandlesAuthorization;

class BancoPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Banco');
    }

    public function view(AuthUser $authUser, Banco $banco): bool
    {
        return $authUser->can('View:Banco');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Banco');
    }

    public function update(AuthUser $authUser, Banco $banco): bool
    {
        return $authUser->can('Update:Banco');
    }

    public function delete(AuthUser $authUser, Banco $banco): bool
    {
        return $authUser->can('Delete:Banco');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Banco');
    }

    public function restore(AuthUser $authUser, Banco $banco): bool
    {
        return $authUser->can('Restore:Banco');
    }

    public function forceDelete(AuthUser $authUser, Banco $banco): bool
    {
        return $authUser->can('ForceDelete:Banco');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Banco');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Banco');
    }

    public function replicate(AuthUser $authUser, Banco $banco): bool
    {
        return $authUser->can('Replicate:Banco');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Banco');
    }

}
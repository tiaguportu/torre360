<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Fatura;
use Illuminate\Auth\Access\HandlesAuthorization;

class FaturaPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Fatura');
    }

    public function view(AuthUser $authUser, Fatura $fatura): bool
    {
        return $authUser->can('View:Fatura');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Fatura');
    }

    public function update(AuthUser $authUser, Fatura $fatura): bool
    {
        return $authUser->can('Update:Fatura');
    }

    public function delete(AuthUser $authUser, Fatura $fatura): bool
    {
        return $authUser->can('Delete:Fatura');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Fatura');
    }

    public function restore(AuthUser $authUser, Fatura $fatura): bool
    {
        return $authUser->can('Restore:Fatura');
    }

    public function forceDelete(AuthUser $authUser, Fatura $fatura): bool
    {
        return $authUser->can('ForceDelete:Fatura');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Fatura');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Fatura');
    }

    public function replicate(AuthUser $authUser, Fatura $fatura): bool
    {
        return $authUser->can('Replicate:Fatura');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Fatura');
    }

}
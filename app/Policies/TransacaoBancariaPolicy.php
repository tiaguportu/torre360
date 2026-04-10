<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\TransacaoBancaria;
use Illuminate\Auth\Access\HandlesAuthorization;

class TransacaoBancariaPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TransacaoBancaria');
    }

    public function view(AuthUser $authUser, TransacaoBancaria $transacaoBancaria): bool
    {
        return $authUser->can('View:TransacaoBancaria');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TransacaoBancaria');
    }

    public function update(AuthUser $authUser, TransacaoBancaria $transacaoBancaria): bool
    {
        return $authUser->can('Update:TransacaoBancaria');
    }

    public function delete(AuthUser $authUser, TransacaoBancaria $transacaoBancaria): bool
    {
        return $authUser->can('Delete:TransacaoBancaria');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:TransacaoBancaria');
    }

    public function restore(AuthUser $authUser, TransacaoBancaria $transacaoBancaria): bool
    {
        return $authUser->can('Restore:TransacaoBancaria');
    }

    public function forceDelete(AuthUser $authUser, TransacaoBancaria $transacaoBancaria): bool
    {
        return $authUser->can('ForceDelete:TransacaoBancaria');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TransacaoBancaria');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TransacaoBancaria');
    }

    public function replicate(AuthUser $authUser, TransacaoBancaria $transacaoBancaria): bool
    {
        return $authUser->can('Replicate:TransacaoBancaria');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:TransacaoBancaria');
    }

}
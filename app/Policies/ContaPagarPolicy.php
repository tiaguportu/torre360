<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ContaPagar;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ContaPagarPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ContaPagar');
    }

    public function view(AuthUser $authUser, ContaPagar $contaPagar): bool
    {
        return $authUser->can('View:ContaPagar');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ContaPagar');
    }

    public function update(AuthUser $authUser, ContaPagar $contaPagar): bool
    {
        return $authUser->can('Update:ContaPagar');
    }

    public function delete(AuthUser $authUser, ContaPagar $contaPagar): bool
    {
        return $authUser->can('Delete:ContaPagar');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ContaPagar');
    }

    public function restore(AuthUser $authUser, ContaPagar $contaPagar): bool
    {
        return $authUser->can('Restore:ContaPagar');
    }

    public function forceDelete(AuthUser $authUser, ContaPagar $contaPagar): bool
    {
        return $authUser->can('ForceDelete:ContaPagar');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ContaPagar');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ContaPagar');
    }

    public function replicate(AuthUser $authUser, ContaPagar $contaPagar): bool
    {
        return $authUser->can('Replicate:ContaPagar');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ContaPagar');
    }
}

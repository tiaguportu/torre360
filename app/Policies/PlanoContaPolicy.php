<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PlanoConta;
use Illuminate\Auth\Access\HandlesAuthorization;

class PlanoContaPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PlanoConta');
    }

    public function view(AuthUser $authUser, PlanoConta $planoConta): bool
    {
        return $authUser->can('View:PlanoConta');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PlanoConta');
    }

    public function update(AuthUser $authUser, PlanoConta $planoConta): bool
    {
        return $authUser->can('Update:PlanoConta');
    }

    public function delete(AuthUser $authUser, PlanoConta $planoConta): bool
    {
        return $authUser->can('Delete:PlanoConta');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:PlanoConta');
    }

    public function restore(AuthUser $authUser, PlanoConta $planoConta): bool
    {
        return $authUser->can('Restore:PlanoConta');
    }

    public function forceDelete(AuthUser $authUser, PlanoConta $planoConta): bool
    {
        return $authUser->can('ForceDelete:PlanoConta');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PlanoConta');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PlanoConta');
    }

    public function replicate(AuthUser $authUser, PlanoConta $planoConta): bool
    {
        return $authUser->can('Replicate:PlanoConta');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PlanoConta');
    }

}
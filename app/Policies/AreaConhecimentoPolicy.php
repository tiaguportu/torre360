<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AreaConhecimento;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class AreaConhecimentoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AreaConhecimento');
    }

    public function view(AuthUser $authUser, AreaConhecimento $areaConhecimento): bool
    {
        return $authUser->can('View:AreaConhecimento');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AreaConhecimento');
    }

    public function update(AuthUser $authUser, AreaConhecimento $areaConhecimento): bool
    {
        return $authUser->can('Update:AreaConhecimento');
    }

    public function delete(AuthUser $authUser, AreaConhecimento $areaConhecimento): bool
    {
        return $authUser->can('Delete:AreaConhecimento');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:AreaConhecimento');
    }

    public function restore(AuthUser $authUser, AreaConhecimento $areaConhecimento): bool
    {
        return $authUser->can('Restore:AreaConhecimento');
    }

    public function forceDelete(AuthUser $authUser, AreaConhecimento $areaConhecimento): bool
    {
        return $authUser->can('ForceDelete:AreaConhecimento');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AreaConhecimento');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AreaConhecimento');
    }

    public function replicate(AuthUser $authUser, AreaConhecimento $areaConhecimento): bool
    {
        return $authUser->can('Replicate:AreaConhecimento');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AreaConhecimento');
    }
}

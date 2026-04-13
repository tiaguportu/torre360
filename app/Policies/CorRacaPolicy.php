<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CorRaca;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CorRacaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CorRaca');
    }

    public function view(AuthUser $authUser, CorRaca $corRaca): bool
    {
        return $authUser->can('View:CorRaca');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CorRaca');
    }

    public function update(AuthUser $authUser, CorRaca $corRaca): bool
    {
        return $authUser->can('Update:CorRaca');
    }

    public function delete(AuthUser $authUser, CorRaca $corRaca): bool
    {
        return $authUser->can('Delete:CorRaca');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CorRaca');
    }

    public function restore(AuthUser $authUser, CorRaca $corRaca): bool
    {
        return $authUser->can('Restore:CorRaca');
    }

    public function forceDelete(AuthUser $authUser, CorRaca $corRaca): bool
    {
        return $authUser->can('ForceDelete:CorRaca');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CorRaca');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CorRaca');
    }

    public function replicate(AuthUser $authUser, CorRaca $corRaca): bool
    {
        return $authUser->can('Replicate:CorRaca');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CorRaca');
    }
}

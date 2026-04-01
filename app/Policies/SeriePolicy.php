<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Serie;
use Illuminate\Auth\Access\HandlesAuthorization;

class SeriePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Serie');
    }

    public function view(AuthUser $authUser, Serie $serie): bool
    {
        return $authUser->can('View:Serie');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Serie');
    }

    public function update(AuthUser $authUser, Serie $serie): bool
    {
        return $authUser->can('Update:Serie');
    }

    public function delete(AuthUser $authUser, Serie $serie): bool
    {
        return $authUser->can('Delete:Serie');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Serie');
    }

    public function restore(AuthUser $authUser, Serie $serie): bool
    {
        return $authUser->can('Restore:Serie');
    }

    public function forceDelete(AuthUser $authUser, Serie $serie): bool
    {
        return $authUser->can('ForceDelete:Serie');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Serie');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Serie');
    }

    public function replicate(AuthUser $authUser, Serie $serie): bool
    {
        return $authUser->can('Replicate:Serie');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Serie');
    }

}
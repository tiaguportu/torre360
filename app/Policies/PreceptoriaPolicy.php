<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Preceptoria;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PreceptoriaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Preceptoria');
    }

    public function view(AuthUser $authUser, Preceptoria $preceptoria): bool
    {
        return $authUser->can('View:Preceptoria');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Preceptoria');
    }

    public function update(AuthUser $authUser, Preceptoria $preceptoria): bool
    {
        return $authUser->can('Update:Preceptoria');
    }

    public function delete(AuthUser $authUser, Preceptoria $preceptoria): bool
    {
        return $authUser->can('Delete:Preceptoria');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Preceptoria');
    }

    public function restore(AuthUser $authUser, Preceptoria $preceptoria): bool
    {
        return $authUser->can('Restore:Preceptoria');
    }

    public function forceDelete(AuthUser $authUser, Preceptoria $preceptoria): bool
    {
        return $authUser->can('ForceDelete:Preceptoria');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Preceptoria');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Preceptoria');
    }

    public function replicate(AuthUser $authUser, Preceptoria $preceptoria): bool
    {
        return $authUser->can('Replicate:Preceptoria');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Preceptoria');
    }
}

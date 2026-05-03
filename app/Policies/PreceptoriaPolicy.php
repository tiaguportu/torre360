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
        return $authUser->hasPermissionInActiveRole('ViewAny:Preceptoria');
    }

    public function view(AuthUser $authUser, Preceptoria $preceptoria): bool
    {
        return $authUser->hasPermissionInActiveRole('View:Preceptoria');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->hasPermissionInActiveRole('Create:Preceptoria');
    }

    public function update(AuthUser $authUser, Preceptoria $preceptoria): bool
    {
        return $authUser->hasPermissionInActiveRole('Update:Preceptoria');
    }

    public function delete(AuthUser $authUser, Preceptoria $preceptoria): bool
    {
        return $authUser->hasPermissionInActiveRole('Delete:Preceptoria');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->hasPermissionInActiveRole('DeleteAny:Preceptoria');
    }

    public function restore(AuthUser $authUser, Preceptoria $preceptoria): bool
    {
        return $authUser->hasPermissionInActiveRole('Restore:Preceptoria');
    }

    public function forceDelete(AuthUser $authUser, Preceptoria $preceptoria): bool
    {
        return $authUser->hasPermissionInActiveRole('ForceDelete:Preceptoria');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->hasPermissionInActiveRole('ForceDeleteAny:Preceptoria');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->hasPermissionInActiveRole('RestoreAny:Preceptoria');
    }

    public function replicate(AuthUser $authUser, Preceptoria $preceptoria): bool
    {
        return $authUser->hasPermissionInActiveRole('Replicate:Preceptoria');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->hasPermissionInActiveRole('Reorder:Preceptoria');
    }

    public function agendar(AuthUser $authUser, Preceptoria $preceptoria): bool
    {
        return $authUser->hasPermissionInActiveRole('Agendar:Preceptoria');
    }
}

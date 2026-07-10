<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\CicloPreceptoria;
use Illuminate\Auth\Access\HandlesAuthorization;

class CicloPreceptoriaPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CicloPreceptoria');
    }

    public function view(AuthUser $authUser, CicloPreceptoria $cicloPreceptoria): bool
    {
        return $authUser->can('View:CicloPreceptoria');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CicloPreceptoria');
    }

    public function update(AuthUser $authUser, CicloPreceptoria $cicloPreceptoria): bool
    {
        return $authUser->can('Update:CicloPreceptoria');
    }

    public function delete(AuthUser $authUser, CicloPreceptoria $cicloPreceptoria): bool
    {
        return $authUser->can('Delete:CicloPreceptoria');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CicloPreceptoria');
    }

    public function restore(AuthUser $authUser, CicloPreceptoria $cicloPreceptoria): bool
    {
        return $authUser->can('Restore:CicloPreceptoria');
    }

    public function forceDelete(AuthUser $authUser, CicloPreceptoria $cicloPreceptoria): bool
    {
        return $authUser->can('ForceDelete:CicloPreceptoria');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CicloPreceptoria');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CicloPreceptoria');
    }

    public function replicate(AuthUser $authUser, CicloPreceptoria $cicloPreceptoria): bool
    {
        return $authUser->can('Replicate:CicloPreceptoria');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CicloPreceptoria');
    }

}
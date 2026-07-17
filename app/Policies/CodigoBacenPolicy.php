<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CodigoBacen;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CodigoBacenPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CodigoBacen');
    }

    public function view(AuthUser $authUser, CodigoBacen $codigoBacen): bool
    {
        return $authUser->can('View:CodigoBacen');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CodigoBacen');
    }

    public function update(AuthUser $authUser, CodigoBacen $codigoBacen): bool
    {
        return $authUser->can('Update:CodigoBacen');
    }

    public function delete(AuthUser $authUser, CodigoBacen $codigoBacen): bool
    {
        return $authUser->can('Delete:CodigoBacen');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CodigoBacen');
    }

    public function restore(AuthUser $authUser, CodigoBacen $codigoBacen): bool
    {
        return $authUser->can('Restore:CodigoBacen');
    }

    public function forceDelete(AuthUser $authUser, CodigoBacen $codigoBacen): bool
    {
        return $authUser->can('ForceDelete:CodigoBacen');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CodigoBacen');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CodigoBacen');
    }

    public function replicate(AuthUser $authUser, CodigoBacen $codigoBacen): bool
    {
        return $authUser->can('Replicate:CodigoBacen');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CodigoBacen');
    }
}

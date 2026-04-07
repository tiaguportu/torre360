<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TipoVinculo;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class TipoVinculoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TipoVinculo');
    }

    public function view(AuthUser $authUser, TipoVinculo $tipoVinculo): bool
    {
        return $authUser->can('View:TipoVinculo');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TipoVinculo');
    }

    public function update(AuthUser $authUser, TipoVinculo $tipoVinculo): bool
    {
        return $authUser->can('Update:TipoVinculo');
    }

    public function delete(AuthUser $authUser, TipoVinculo $tipoVinculo): bool
    {
        return $authUser->can('Delete:TipoVinculo');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:TipoVinculo');
    }

    public function restore(AuthUser $authUser, TipoVinculo $tipoVinculo): bool
    {
        return $authUser->can('Restore:TipoVinculo');
    }

    public function forceDelete(AuthUser $authUser, TipoVinculo $tipoVinculo): bool
    {
        return $authUser->can('ForceDelete:TipoVinculo');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TipoVinculo');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TipoVinculo');
    }

    public function replicate(AuthUser $authUser, TipoVinculo $tipoVinculo): bool
    {
        return $authUser->can('Replicate:TipoVinculo');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:TipoVinculo');
    }
}

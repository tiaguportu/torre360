<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TipoOcorrencia;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class TipoOcorrenciaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TipoOcorrencia');
    }

    public function view(AuthUser $authUser, TipoOcorrencia $tipoOcorrencia): bool
    {
        return $authUser->can('View:TipoOcorrencia');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TipoOcorrencia');
    }

    public function update(AuthUser $authUser, TipoOcorrencia $tipoOcorrencia): bool
    {
        return $authUser->can('Update:TipoOcorrencia');
    }

    public function delete(AuthUser $authUser, TipoOcorrencia $tipoOcorrencia): bool
    {
        return $authUser->can('Delete:TipoOcorrencia');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:TipoOcorrencia');
    }

    public function restore(AuthUser $authUser, TipoOcorrencia $tipoOcorrencia): bool
    {
        return $authUser->can('Restore:TipoOcorrencia');
    }

    public function forceDelete(AuthUser $authUser, TipoOcorrencia $tipoOcorrencia): bool
    {
        return $authUser->can('ForceDelete:TipoOcorrencia');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TipoOcorrencia');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TipoOcorrencia');
    }

    public function replicate(AuthUser $authUser, TipoOcorrencia $tipoOcorrencia): bool
    {
        return $authUser->can('Replicate:TipoOcorrencia');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:TipoOcorrencia');
    }
}

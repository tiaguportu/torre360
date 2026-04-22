<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CampoExperiencia;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CampoExperienciaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CampoExperiencia');
    }

    public function view(AuthUser $authUser, CampoExperiencia $campoExperiencia): bool
    {
        return $authUser->can('View:CampoExperiencia');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CampoExperiencia');
    }

    public function update(AuthUser $authUser, CampoExperiencia $campoExperiencia): bool
    {
        return $authUser->can('Update:CampoExperiencia');
    }

    public function delete(AuthUser $authUser, CampoExperiencia $campoExperiencia): bool
    {
        return $authUser->can('Delete:CampoExperiencia');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CampoExperiencia');
    }

    public function restore(AuthUser $authUser, CampoExperiencia $campoExperiencia): bool
    {
        return $authUser->can('Restore:CampoExperiencia');
    }

    public function forceDelete(AuthUser $authUser, CampoExperiencia $campoExperiencia): bool
    {
        return $authUser->can('ForceDelete:CampoExperiencia');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CampoExperiencia');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CampoExperiencia');
    }

    public function replicate(AuthUser $authUser, CampoExperiencia $campoExperiencia): bool
    {
        return $authUser->can('Replicate:CampoExperiencia');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CampoExperiencia');
    }
}

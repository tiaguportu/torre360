<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\FichaMedica;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class FichaMedicaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:FichaMedica');
    }

    public function view(AuthUser $authUser, FichaMedica $fichaMedica): bool
    {
        return $authUser->can('View:FichaMedica');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:FichaMedica');
    }

    public function update(AuthUser $authUser, FichaMedica $fichaMedica): bool
    {
        return $authUser->can('Update:FichaMedica');
    }

    public function delete(AuthUser $authUser, FichaMedica $fichaMedica): bool
    {
        return $authUser->can('Delete:FichaMedica');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:FichaMedica');
    }

    public function restore(AuthUser $authUser, FichaMedica $fichaMedica): bool
    {
        return $authUser->can('Restore:FichaMedica');
    }

    public function forceDelete(AuthUser $authUser, FichaMedica $fichaMedica): bool
    {
        return $authUser->can('ForceDelete:FichaMedica');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:FichaMedica');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:FichaMedica');
    }

    public function replicate(AuthUser $authUser, FichaMedica $fichaMedica): bool
    {
        return $authUser->can('Replicate:FichaMedica');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:FichaMedica');
    }
}

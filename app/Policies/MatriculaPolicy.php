<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Matricula;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class MatriculaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Matricula');
    }

    public function view(AuthUser $authUser, Matricula $matricula): bool
    {
        return $authUser->can('View:Matricula');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Matricula');
    }

    public function update(AuthUser $authUser, Matricula $matricula): bool
    {
        return $authUser->can('Update:Matricula');
    }

    public function delete(AuthUser $authUser, Matricula $matricula): bool
    {
        return $authUser->can('Delete:Matricula');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Matricula');
    }

    public function restore(AuthUser $authUser, Matricula $matricula): bool
    {
        return $authUser->can('Restore:Matricula');
    }

    public function forceDelete(AuthUser $authUser, Matricula $matricula): bool
    {
        return $authUser->can('ForceDelete:Matricula');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Matricula');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Matricula');
    }

    public function replicate(AuthUser $authUser, Matricula $matricula): bool
    {
        return $authUser->can('Replicate:Matricula');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Matricula');
    }

    public function documentos(AuthUser $authUser, Matricula $matricula): bool
    {
        return $authUser->can('Documentos:Matricula');
    }

    public function avisarPendencia(AuthUser $authUser, Matricula $matricula): bool
    {
        return $authUser->can('AvisarPendencia:Matricula');
    }
}

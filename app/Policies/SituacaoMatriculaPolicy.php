<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SituacaoMatricula;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class SituacaoMatriculaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SituacaoMatricula');
    }

    public function view(AuthUser $authUser, SituacaoMatricula $situacaoMatricula): bool
    {
        return $authUser->can('View:SituacaoMatricula');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SituacaoMatricula');
    }

    public function update(AuthUser $authUser, SituacaoMatricula $situacaoMatricula): bool
    {
        return $authUser->can('Update:SituacaoMatricula');
    }

    public function delete(AuthUser $authUser, SituacaoMatricula $situacaoMatricula): bool
    {
        return $authUser->can('Delete:SituacaoMatricula');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:SituacaoMatricula');
    }

    public function restore(AuthUser $authUser, SituacaoMatricula $situacaoMatricula): bool
    {
        return $authUser->can('Restore:SituacaoMatricula');
    }

    public function forceDelete(AuthUser $authUser, SituacaoMatricula $situacaoMatricula): bool
    {
        return $authUser->can('ForceDelete:SituacaoMatricula');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SituacaoMatricula');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SituacaoMatricula');
    }

    public function replicate(AuthUser $authUser, SituacaoMatricula $situacaoMatricula): bool
    {
        return $authUser->can('Replicate:SituacaoMatricula');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SituacaoMatricula');
    }
}

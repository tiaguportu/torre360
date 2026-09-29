<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MatrizCurricular;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class MatrizCurricularPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MatrizCurricular');
    }

    public function view(AuthUser $authUser, MatrizCurricular $matrizCurricular): bool
    {
        return $authUser->can('View:MatrizCurricular');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MatrizCurricular');
    }

    public function update(AuthUser $authUser, MatrizCurricular $matrizCurricular): bool
    {
        return $authUser->can('Update:MatrizCurricular');
    }

    public function delete(AuthUser $authUser, MatrizCurricular $matrizCurricular): bool
    {
        return $authUser->can('Delete:MatrizCurricular');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:MatrizCurricular');
    }

    public function restore(AuthUser $authUser, MatrizCurricular $matrizCurricular): bool
    {
        return $authUser->can('Restore:MatrizCurricular');
    }

    public function forceDelete(AuthUser $authUser, MatrizCurricular $matrizCurricular): bool
    {
        return $authUser->can('ForceDelete:MatrizCurricular');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MatrizCurricular');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MatrizCurricular');
    }

    public function replicate(AuthUser $authUser, MatrizCurricular $matrizCurricular): bool
    {
        return $authUser->can('Replicate:MatrizCurricular');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MatrizCurricular');
    }
}

<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\InstituicaoEnsino;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class InstituicaoEnsinoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:InstituicaoEnsino');
    }

    public function view(AuthUser $authUser, InstituicaoEnsino $instituicaoEnsino): bool
    {
        return $authUser->can('View:InstituicaoEnsino');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:InstituicaoEnsino');
    }

    public function update(AuthUser $authUser, InstituicaoEnsino $instituicaoEnsino): bool
    {
        return $authUser->can('Update:InstituicaoEnsino');
    }

    public function delete(AuthUser $authUser, InstituicaoEnsino $instituicaoEnsino): bool
    {
        return $authUser->can('Delete:InstituicaoEnsino');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:InstituicaoEnsino');
    }

    public function restore(AuthUser $authUser, InstituicaoEnsino $instituicaoEnsino): bool
    {
        return $authUser->can('Restore:InstituicaoEnsino');
    }

    public function forceDelete(AuthUser $authUser, InstituicaoEnsino $instituicaoEnsino): bool
    {
        return $authUser->can('ForceDelete:InstituicaoEnsino');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:InstituicaoEnsino');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:InstituicaoEnsino');
    }

    public function replicate(AuthUser $authUser, InstituicaoEnsino $instituicaoEnsino): bool
    {
        return $authUser->can('Replicate:InstituicaoEnsino');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:InstituicaoEnsino');
    }
}

<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Disciplina;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class DisciplinaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Disciplina');
    }

    public function view(AuthUser $authUser, Disciplina $disciplina): bool
    {
        return $authUser->can('View:Disciplina');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Disciplina');
    }

    public function update(AuthUser $authUser, Disciplina $disciplina): bool
    {
        return $authUser->can('Update:Disciplina');
    }

    public function delete(AuthUser $authUser, Disciplina $disciplina): bool
    {
        return $authUser->can('Delete:Disciplina');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Disciplina');
    }

    public function restore(AuthUser $authUser, Disciplina $disciplina): bool
    {
        return $authUser->can('Restore:Disciplina');
    }

    public function forceDelete(AuthUser $authUser, Disciplina $disciplina): bool
    {
        return $authUser->can('ForceDelete:Disciplina');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Disciplina');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Disciplina');
    }

    public function replicate(AuthUser $authUser, Disciplina $disciplina): bool
    {
        return $authUser->can('Replicate:Disciplina');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Disciplina');
    }
}

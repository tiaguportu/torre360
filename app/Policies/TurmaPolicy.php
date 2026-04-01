<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Turma;
use Illuminate\Auth\Access\HandlesAuthorization;

class TurmaPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Turma');
    }

    public function view(AuthUser $authUser, Turma $turma): bool
    {
        return $authUser->can('View:Turma');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Turma');
    }

    public function update(AuthUser $authUser, Turma $turma): bool
    {
        return $authUser->can('Update:Turma');
    }

    public function delete(AuthUser $authUser, Turma $turma): bool
    {
        return $authUser->can('Delete:Turma');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Turma');
    }

    public function restore(AuthUser $authUser, Turma $turma): bool
    {
        return $authUser->can('Restore:Turma');
    }

    public function forceDelete(AuthUser $authUser, Turma $turma): bool
    {
        return $authUser->can('ForceDelete:Turma');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Turma');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Turma');
    }

    public function replicate(AuthUser $authUser, Turma $turma): bool
    {
        return $authUser->can('Replicate:Turma');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Turma');
    }

}
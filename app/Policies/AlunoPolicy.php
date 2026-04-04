<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Aluno;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class AlunoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Aluno');
    }

    public function view(AuthUser $authUser, Aluno $aluno): bool
    {
        return $authUser->can('View:Aluno');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Aluno');
    }

    public function update(AuthUser $authUser, Aluno $aluno): bool
    {
        return $authUser->can('Update:Aluno');
    }

    public function delete(AuthUser $authUser, Aluno $aluno): bool
    {
        return $authUser->can('Delete:Aluno');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Aluno');
    }

    public function restore(AuthUser $authUser, Aluno $aluno): bool
    {
        return $authUser->can('Restore:Aluno');
    }

    public function forceDelete(AuthUser $authUser, Aluno $aluno): bool
    {
        return $authUser->can('ForceDelete:Aluno');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Aluno');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Aluno');
    }

    public function replicate(AuthUser $authUser, Aluno $aluno): bool
    {
        return $authUser->can('Replicate:Aluno');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Aluno');
    }
}

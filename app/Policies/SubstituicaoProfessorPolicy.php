<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SubstituicaoProfessor;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class SubstituicaoProfessorPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SubstituicaoProfessor');
    }

    public function view(AuthUser $authUser, SubstituicaoProfessor $substituicaoProfessor): bool
    {
        return $authUser->can('View:SubstituicaoProfessor');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SubstituicaoProfessor');
    }

    public function update(AuthUser $authUser, SubstituicaoProfessor $substituicaoProfessor): bool
    {
        return $authUser->can('Update:SubstituicaoProfessor');
    }

    public function delete(AuthUser $authUser, SubstituicaoProfessor $substituicaoProfessor): bool
    {
        return $authUser->can('Delete:SubstituicaoProfessor');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:SubstituicaoProfessor');
    }
}

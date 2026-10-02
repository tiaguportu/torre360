<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Emprestimo;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class EmprestimoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Emprestimo');
    }

    public function view(AuthUser $authUser, Emprestimo $emprestimo): bool
    {
        return $authUser->can('View:Emprestimo');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Emprestimo');
    }

    public function update(AuthUser $authUser, Emprestimo $emprestimo): bool
    {
        return $authUser->can('Update:Emprestimo');
    }

    public function delete(AuthUser $authUser, Emprestimo $emprestimo): bool
    {
        return $authUser->can('Delete:Emprestimo');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Emprestimo');
    }
}

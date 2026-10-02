<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Funcionario;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class FuncionarioPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Funcionario');
    }

    public function view(AuthUser $authUser, Funcionario $funcionario): bool
    {
        return $authUser->can('View:Funcionario');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Funcionario');
    }

    public function update(AuthUser $authUser, Funcionario $funcionario): bool
    {
        return $authUser->can('Update:Funcionario');
    }

    public function delete(AuthUser $authUser, Funcionario $funcionario): bool
    {
        return $authUser->can('Delete:Funcionario');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Funcionario');
    }
}

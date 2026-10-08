<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RegistroRotinaDiaria;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class RegistroRotinaDiariaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:RegistroRotinaDiaria');
    }

    public function view(AuthUser $authUser, RegistroRotinaDiaria $registroRotinaDiaria): bool
    {
        return $authUser->can('View:RegistroRotinaDiaria');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RegistroRotinaDiaria');
    }

    public function update(AuthUser $authUser, RegistroRotinaDiaria $registroRotinaDiaria): bool
    {
        return $authUser->can('Update:RegistroRotinaDiaria');
    }

    public function delete(AuthUser $authUser, RegistroRotinaDiaria $registroRotinaDiaria): bool
    {
        return $authUser->can('Delete:RegistroRotinaDiaria');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:RegistroRotinaDiaria');
    }
}

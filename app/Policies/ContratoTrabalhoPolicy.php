<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ContratoTrabalho;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ContratoTrabalhoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ContratoTrabalho');
    }

    public function view(AuthUser $authUser, ContratoTrabalho $contratoTrabalho): bool
    {
        return $authUser->can('View:ContratoTrabalho');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ContratoTrabalho');
    }

    public function update(AuthUser $authUser, ContratoTrabalho $contratoTrabalho): bool
    {
        return $authUser->can('Update:ContratoTrabalho');
    }

    public function delete(AuthUser $authUser, ContratoTrabalho $contratoTrabalho): bool
    {
        return $authUser->can('Delete:ContratoTrabalho');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ContratoTrabalho');
    }
}

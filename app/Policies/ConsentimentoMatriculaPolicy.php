<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ConsentimentoMatricula;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ConsentimentoMatriculaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ConsentimentoMatricula');
    }

    public function view(AuthUser $authUser, ConsentimentoMatricula $consentimentoMatricula): bool
    {
        return $authUser->can('View:ConsentimentoMatricula');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ConsentimentoMatricula');
    }

    public function update(AuthUser $authUser, ConsentimentoMatricula $consentimentoMatricula): bool
    {
        return $authUser->can('Update:ConsentimentoMatricula');
    }

    public function delete(AuthUser $authUser, ConsentimentoMatricula $consentimentoMatricula): bool
    {
        return $authUser->can('Delete:ConsentimentoMatricula');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ConsentimentoMatricula');
    }
}

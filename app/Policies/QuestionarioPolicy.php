<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Questionario;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class QuestionarioPolicy
{
    use HandlesAuthorization;

    public function before(AuthUser $authUser, string $ability): ?bool
    {
        /** @var User $authUser */
        if ($authUser->hasRole('super_admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Questionario');
    }

    public function view(AuthUser $authUser, Questionario $questionario): bool
    {
        /** @var User $authUser */
        if ($questionario->ehDono($authUser) || $questionario->ehObservador($authUser) || $questionario->podeSerRespondidoPor($authUser)) {
            return true;
        }

        return $authUser->can('View:Questionario');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Questionario');
    }

    public function update(AuthUser $authUser, Questionario $questionario): bool
    {
        /** @var User $authUser */
        if ($questionario->ehDono($authUser)) {
            return true;
        }

        return $authUser->can('Update:Questionario');
    }

    public function delete(AuthUser $authUser, Questionario $questionario): bool
    {
        /** @var User $authUser */
        if ($questionario->ehDono($authUser)) {
            return true;
        }

        return $authUser->can('Delete:Questionario');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Questionario');
    }

    public function restore(AuthUser $authUser, Questionario $questionario): bool
    {
        return $authUser->can('Restore:Questionario');
    }

    public function forceDelete(AuthUser $authUser, Questionario $questionario): bool
    {
        return $authUser->can('ForceDelete:Questionario');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Questionario');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Questionario');
    }

    public function replicate(AuthUser $authUser, Questionario $questionario): bool
    {
        return $authUser->can('Replicate:Questionario');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Questionario');
    }
}

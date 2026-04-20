<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Questionario;
use Illuminate\Auth\Access\HandlesAuthorization;

class QuestionarioPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Questionario');
    }

    public function view(AuthUser $authUser, Questionario $questionario): bool
    {
        return $authUser->can('View:Questionario');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Questionario');
    }

    public function update(AuthUser $authUser, Questionario $questionario): bool
    {
        return $authUser->can('Update:Questionario');
    }

    public function delete(AuthUser $authUser, Questionario $questionario): bool
    {
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

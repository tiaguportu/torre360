<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Habilidade;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class HabilidadePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Habilidade');
    }

    public function view(AuthUser $authUser, Habilidade $habilidade): bool
    {
        return $authUser->can('View:Habilidade');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Habilidade');
    }

    public function update(AuthUser $authUser, Habilidade $habilidade): bool
    {
        return $authUser->can('Update:Habilidade');
    }

    public function delete(AuthUser $authUser, Habilidade $habilidade): bool
    {
        return $authUser->can('Delete:Habilidade');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Habilidade');
    }

    public function restore(AuthUser $authUser, Habilidade $habilidade): bool
    {
        return $authUser->can('Restore:Habilidade');
    }

    public function forceDelete(AuthUser $authUser, Habilidade $habilidade): bool
    {
        return $authUser->can('ForceDelete:Habilidade');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Habilidade');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Habilidade');
    }

    public function replicate(AuthUser $authUser, Habilidade $habilidade): bool
    {
        return $authUser->can('Replicate:Habilidade');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Habilidade');
    }
}

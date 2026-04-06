<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Titulo;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class TituloPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Titulo');
    }

    public function view(AuthUser $authUser, Titulo $titulo): bool
    {
        return $authUser->can('View:Titulo');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Titulo');
    }

    public function update(AuthUser $authUser, Titulo $titulo): bool
    {
        return $authUser->can('Update:Titulo');
    }

    public function delete(AuthUser $authUser, Titulo $titulo): bool
    {
        return $authUser->can('Delete:Titulo');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Titulo');
    }

    public function restore(AuthUser $authUser, Titulo $titulo): bool
    {
        return $authUser->can('Restore:Titulo');
    }

    public function forceDelete(AuthUser $authUser, Titulo $titulo): bool
    {
        return $authUser->can('ForceDelete:Titulo');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Titulo');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Titulo');
    }

    public function replicate(AuthUser $authUser, Titulo $titulo): bool
    {
        return $authUser->can('Replicate:Titulo');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Titulo');
    }
}

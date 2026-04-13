<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CategoriaOs;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CategoriaOsPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CategoriaOs');
    }

    public function view(AuthUser $authUser, CategoriaOs $categoriaOs): bool
    {
        return $authUser->can('View:CategoriaOs');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CategoriaOs');
    }

    public function update(AuthUser $authUser, CategoriaOs $categoriaOs): bool
    {
        return $authUser->can('Update:CategoriaOs');
    }

    public function delete(AuthUser $authUser, CategoriaOs $categoriaOs): bool
    {
        return $authUser->can('Delete:CategoriaOs');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CategoriaOs');
    }

    public function restore(AuthUser $authUser, CategoriaOs $categoriaOs): bool
    {
        return $authUser->can('Restore:CategoriaOs');
    }

    public function forceDelete(AuthUser $authUser, CategoriaOs $categoriaOs): bool
    {
        return $authUser->can('ForceDelete:CategoriaOs');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CategoriaOs');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CategoriaOs');
    }

    public function replicate(AuthUser $authUser, CategoriaOs $categoriaOs): bool
    {
        return $authUser->can('Replicate:CategoriaOs');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CategoriaOs');
    }
}

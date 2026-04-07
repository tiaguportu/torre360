<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\CategoriaOs;
use Illuminate\Auth\Access\HandlesAuthorization;

class CategoriaOsPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_categoria::os');
    }

    public function view(AuthUser $authUser, CategoriaOs $categoriaOs): bool
    {
        return $authUser->can('view_categoria::os');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_categoria::os');
    }

    public function update(AuthUser $authUser, CategoriaOs $categoriaOs): bool
    {
        return $authUser->can('update_categoria::os');
    }

    public function delete(AuthUser $authUser, CategoriaOs $categoriaOs): bool
    {
        return $authUser->can('delete_categoria::os');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete_any_categoria::os');
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
<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\CategoriaAvaliacao;
use Illuminate\Auth\Access\HandlesAuthorization;

class CategoriaAvaliacaoPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CategoriaAvaliacao');
    }

    public function view(AuthUser $authUser, CategoriaAvaliacao $categoriaAvaliacao): bool
    {
        return $authUser->can('View:CategoriaAvaliacao');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CategoriaAvaliacao');
    }

    public function update(AuthUser $authUser, CategoriaAvaliacao $categoriaAvaliacao): bool
    {
        return $authUser->can('Update:CategoriaAvaliacao');
    }

    public function delete(AuthUser $authUser, CategoriaAvaliacao $categoriaAvaliacao): bool
    {
        return $authUser->can('Delete:CategoriaAvaliacao');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CategoriaAvaliacao');
    }

    public function restore(AuthUser $authUser, CategoriaAvaliacao $categoriaAvaliacao): bool
    {
        return $authUser->can('Restore:CategoriaAvaliacao');
    }

    public function forceDelete(AuthUser $authUser, CategoriaAvaliacao $categoriaAvaliacao): bool
    {
        return $authUser->can('ForceDelete:CategoriaAvaliacao');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CategoriaAvaliacao');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CategoriaAvaliacao');
    }

    public function replicate(AuthUser $authUser, CategoriaAvaliacao $categoriaAvaliacao): bool
    {
        return $authUser->can('Replicate:CategoriaAvaliacao');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CategoriaAvaliacao');
    }

}
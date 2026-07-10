<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Endereco;
use Illuminate\Auth\Access\HandlesAuthorization;

class EnderecoPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Endereco');
    }

    public function view(AuthUser $authUser, Endereco $endereco): bool
    {
        return $authUser->can('View:Endereco');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Endereco');
    }

    public function update(AuthUser $authUser, Endereco $endereco): bool
    {
        return $authUser->can('Update:Endereco');
    }

    public function delete(AuthUser $authUser, Endereco $endereco): bool
    {
        return $authUser->can('Delete:Endereco');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Endereco');
    }

    public function restore(AuthUser $authUser, Endereco $endereco): bool
    {
        return $authUser->can('Restore:Endereco');
    }

    public function forceDelete(AuthUser $authUser, Endereco $endereco): bool
    {
        return $authUser->can('ForceDelete:Endereco');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Endereco');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Endereco');
    }

    public function replicate(AuthUser $authUser, Endereco $endereco): bool
    {
        return $authUser->can('Replicate:Endereco');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Endereco');
    }

}
<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Fornecedor;
use Illuminate\Auth\Access\HandlesAuthorization;

class FornecedorPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Fornecedor');
    }

    public function view(AuthUser $authUser, Fornecedor $fornecedor): bool
    {
        return $authUser->can('View:Fornecedor');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Fornecedor');
    }

    public function update(AuthUser $authUser, Fornecedor $fornecedor): bool
    {
        return $authUser->can('Update:Fornecedor');
    }

    public function delete(AuthUser $authUser, Fornecedor $fornecedor): bool
    {
        return $authUser->can('Delete:Fornecedor');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Fornecedor');
    }

    public function restore(AuthUser $authUser, Fornecedor $fornecedor): bool
    {
        return $authUser->can('Restore:Fornecedor');
    }

    public function forceDelete(AuthUser $authUser, Fornecedor $fornecedor): bool
    {
        return $authUser->can('ForceDelete:Fornecedor');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Fornecedor');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Fornecedor');
    }

    public function replicate(AuthUser $authUser, Fornecedor $fornecedor): bool
    {
        return $authUser->can('Replicate:Fornecedor');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Fornecedor');
    }

}
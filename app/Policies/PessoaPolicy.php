<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Pessoa;
use Illuminate\Auth\Access\HandlesAuthorization;

class PessoaPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Pessoa');
    }

    public function view(AuthUser $authUser, Pessoa $pessoa): bool
    {
        return $authUser->can('View:Pessoa');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Pessoa');
    }

    public function update(AuthUser $authUser, Pessoa $pessoa): bool
    {
        return $authUser->can('Update:Pessoa');
    }

    public function delete(AuthUser $authUser, Pessoa $pessoa): bool
    {
        return $authUser->can('Delete:Pessoa');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Pessoa');
    }

    public function restore(AuthUser $authUser, Pessoa $pessoa): bool
    {
        return $authUser->can('Restore:Pessoa');
    }

    public function forceDelete(AuthUser $authUser, Pessoa $pessoa): bool
    {
        return $authUser->can('ForceDelete:Pessoa');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Pessoa');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Pessoa');
    }

    public function replicate(AuthUser $authUser, Pessoa $pessoa): bool
    {
        return $authUser->can('Replicate:Pessoa');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Pessoa');
    }

    public function import(AuthUser $authUser, Pessoa $pessoa): bool
    {
        return $authUser->can('Import:Pessoa');
    }

    public function export(AuthUser $authUser, Pessoa $pessoa): bool
    {
        return $authUser->can('Export:Pessoa');
    }

    public function attachEndereco(AuthUser $authUser, Pessoa $pessoa): bool
    {
        return $authUser->can('AttachEndereco:Pessoa');
    }

    public function detachEndereco(AuthUser $authUser, Pessoa $pessoa): bool
    {
        return $authUser->can('DetachEndereco:Pessoa');
    }

    public function attachAluno(AuthUser $authUser, Pessoa $pessoa): bool
    {
        return $authUser->can('AttachAluno:Pessoa');
    }

    public function detachAluno(AuthUser $authUser, Pessoa $pessoa): bool
    {
        return $authUser->can('DetachAluno:Pessoa');
    }

    public function attachResponsavel(AuthUser $authUser, Pessoa $pessoa): bool
    {
        return $authUser->can('AttachResponsavel:Pessoa');
    }

    public function detachResponsavel(AuthUser $authUser, Pessoa $pessoa): bool
    {
        return $authUser->can('DetachResponsavel:Pessoa');
    }

}
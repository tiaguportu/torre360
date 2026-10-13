<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\NecessidadeEducacaoEspecial;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class NecessidadeEducacaoEspecialPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:NecessidadeEducacaoEspecial');
    }

    public function view(AuthUser $authUser, NecessidadeEducacaoEspecial $record): bool
    {
        return $authUser->can('View:NecessidadeEducacaoEspecial');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:NecessidadeEducacaoEspecial');
    }

    public function update(AuthUser $authUser, NecessidadeEducacaoEspecial $record): bool
    {
        return $authUser->can('Update:NecessidadeEducacaoEspecial');
    }

    public function delete(AuthUser $authUser, NecessidadeEducacaoEspecial $record): bool
    {
        return $authUser->can('Delete:NecessidadeEducacaoEspecial');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:NecessidadeEducacaoEspecial');
    }

    public function restore(AuthUser $authUser, NecessidadeEducacaoEspecial $record): bool
    {
        return $authUser->can('Restore:NecessidadeEducacaoEspecial');
    }

    public function forceDelete(AuthUser $authUser, NecessidadeEducacaoEspecial $record): bool
    {
        return $authUser->can('ForceDelete:NecessidadeEducacaoEspecial');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:NecessidadeEducacaoEspecial');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:NecessidadeEducacaoEspecial');
    }
}

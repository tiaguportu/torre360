<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RecursoAcessibilidade;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class RecursoAcessibilidadePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:RecursoAcessibilidade');
    }

    public function view(AuthUser $authUser, RecursoAcessibilidade $record): bool
    {
        return $authUser->can('View:RecursoAcessibilidade');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RecursoAcessibilidade');
    }

    public function update(AuthUser $authUser, RecursoAcessibilidade $record): bool
    {
        return $authUser->can('Update:RecursoAcessibilidade');
    }

    public function delete(AuthUser $authUser, RecursoAcessibilidade $record): bool
    {
        return $authUser->can('Delete:RecursoAcessibilidade');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:RecursoAcessibilidade');
    }

    public function restore(AuthUser $authUser, RecursoAcessibilidade $record): bool
    {
        return $authUser->can('Restore:RecursoAcessibilidade');
    }

    public function forceDelete(AuthUser $authUser, RecursoAcessibilidade $record): bool
    {
        return $authUser->can('ForceDelete:RecursoAcessibilidade');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:RecursoAcessibilidade');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:RecursoAcessibilidade');
    }
}

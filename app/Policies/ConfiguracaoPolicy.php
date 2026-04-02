<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Configuracao;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ConfiguracaoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Configuracao');
    }

    public function view(AuthUser $authUser, Configuracao $configuracao): bool
    {
        return $authUser->can('View:Configuracao');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Configuracao');
    }

    public function update(AuthUser $authUser, Configuracao $configuracao): bool
    {
        return $authUser->can('Update:Configuracao');
    }

    public function delete(AuthUser $authUser, Configuracao $configuracao): bool
    {
        return $authUser->can('Delete:Configuracao');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Configuracao');
    }

    public function restore(AuthUser $authUser, Configuracao $configuracao): bool
    {
        return $authUser->can('Restore:Configuracao');
    }

    public function forceDelete(AuthUser $authUser, Configuracao $configuracao): bool
    {
        return $authUser->can('ForceDelete:Configuracao');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Configuracao');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Configuracao');
    }

    public function replicate(AuthUser $authUser, Configuracao $configuracao): bool
    {
        return $authUser->can('Replicate:Configuracao');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Configuracao');
    }
}

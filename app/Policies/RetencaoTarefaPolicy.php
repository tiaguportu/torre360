<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RetencaoTarefa;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class RetencaoTarefaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:RetencaoTarefa');
    }

    public function view(AuthUser $authUser, RetencaoTarefa $tarefa): bool
    {
        return $authUser->can('View:RetencaoTarefa');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RetencaoTarefa');
    }

    public function update(AuthUser $authUser, RetencaoTarefa $tarefa): bool
    {
        return $authUser->can('Update:RetencaoTarefa');
    }

    public function delete(AuthUser $authUser, RetencaoTarefa $tarefa): bool
    {
        return $authUser->can('Delete:RetencaoTarefa');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:RetencaoTarefa');
    }

    public function restore(AuthUser $authUser, RetencaoTarefa $tarefa): bool
    {
        return $authUser->can('Restore:RetencaoTarefa');
    }

    public function forceDelete(AuthUser $authUser, RetencaoTarefa $tarefa): bool
    {
        return $authUser->can('ForceDelete:RetencaoTarefa');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:RetencaoTarefa');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:RetencaoTarefa');
    }
}

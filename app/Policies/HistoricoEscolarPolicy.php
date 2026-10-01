<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\HistoricoEscolar;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class HistoricoEscolarPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:HistoricoEscolar');
    }

    public function view(AuthUser $authUser, HistoricoEscolar $historicoEscolar): bool
    {
        return $authUser->can('View:HistoricoEscolar');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:HistoricoEscolar');
    }

    public function update(AuthUser $authUser, HistoricoEscolar $historicoEscolar): bool
    {
        return $authUser->can('Update:HistoricoEscolar');
    }

    public function delete(AuthUser $authUser, HistoricoEscolar $historicoEscolar): bool
    {
        return $authUser->can('Delete:HistoricoEscolar');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:HistoricoEscolar');
    }

    public function restore(AuthUser $authUser, HistoricoEscolar $historicoEscolar): bool
    {
        return $authUser->can('Restore:HistoricoEscolar');
    }

    public function forceDelete(AuthUser $authUser, HistoricoEscolar $historicoEscolar): bool
    {
        return $authUser->can('ForceDelete:HistoricoEscolar');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:HistoricoEscolar');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:HistoricoEscolar');
    }

    public function replicate(AuthUser $authUser, HistoricoEscolar $historicoEscolar): bool
    {
        return $authUser->can('Replicate:HistoricoEscolar');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:HistoricoEscolar');
    }
}

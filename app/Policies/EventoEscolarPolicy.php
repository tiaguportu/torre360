<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EventoEscolar;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class EventoEscolarPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:EventoEscolar');
    }

    public function view(AuthUser $authUser, EventoEscolar $eventoEscolar): bool
    {
        return $authUser->can('View:EventoEscolar');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:EventoEscolar');
    }

    public function update(AuthUser $authUser, EventoEscolar $eventoEscolar): bool
    {
        return $authUser->can('Update:EventoEscolar');
    }

    public function delete(AuthUser $authUser, EventoEscolar $eventoEscolar): bool
    {
        return $authUser->can('Delete:EventoEscolar');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:EventoEscolar');
    }

    public function restore(AuthUser $authUser, EventoEscolar $eventoEscolar): bool
    {
        return $authUser->can('Restore:EventoEscolar');
    }

    public function forceDelete(AuthUser $authUser, EventoEscolar $eventoEscolar): bool
    {
        return $authUser->can('ForceDelete:EventoEscolar');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:EventoEscolar');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:EventoEscolar');
    }

    public function replicate(AuthUser $authUser, EventoEscolar $eventoEscolar): bool
    {
        return $authUser->can('Replicate:EventoEscolar');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:EventoEscolar');
    }
}

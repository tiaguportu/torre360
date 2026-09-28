<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EventoConfirmacao;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class EventoConfirmacaoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:EventoConfirmacao');
    }

    public function view(AuthUser $authUser, EventoConfirmacao $eventoConfirmacao): bool
    {
        return $authUser->can('View:EventoConfirmacao');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:EventoConfirmacao');
    }

    public function update(AuthUser $authUser, EventoConfirmacao $eventoConfirmacao): bool
    {
        return $authUser->can('Update:EventoConfirmacao');
    }

    public function delete(AuthUser $authUser, EventoConfirmacao $eventoConfirmacao): bool
    {
        return $authUser->can('Delete:EventoConfirmacao');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:EventoConfirmacao');
    }

    public function restore(AuthUser $authUser, EventoConfirmacao $eventoConfirmacao): bool
    {
        return $authUser->can('Restore:EventoConfirmacao');
    }

    public function forceDelete(AuthUser $authUser, EventoConfirmacao $eventoConfirmacao): bool
    {
        return $authUser->can('ForceDelete:EventoConfirmacao');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:EventoConfirmacao');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:EventoConfirmacao');
    }

    public function replicate(AuthUser $authUser, EventoConfirmacao $eventoConfirmacao): bool
    {
        return $authUser->can('Replicate:EventoConfirmacao');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:EventoConfirmacao');
    }
}

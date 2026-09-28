<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\VisitaInteressado;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class VisitaInteressadoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:VisitaInteressado');
    }

    public function view(AuthUser $authUser, VisitaInteressado $visitaInteressado): bool
    {
        return $authUser->can('View:VisitaInteressado');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:VisitaInteressado');
    }

    public function update(AuthUser $authUser, VisitaInteressado $visitaInteressado): bool
    {
        return $authUser->can('Update:VisitaInteressado');
    }

    public function delete(AuthUser $authUser, VisitaInteressado $visitaInteressado): bool
    {
        return $authUser->can('Delete:VisitaInteressado');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:VisitaInteressado');
    }

    public function restore(AuthUser $authUser, VisitaInteressado $visitaInteressado): bool
    {
        return $authUser->can('Restore:VisitaInteressado');
    }

    public function forceDelete(AuthUser $authUser, VisitaInteressado $visitaInteressado): bool
    {
        return $authUser->can('ForceDelete:VisitaInteressado');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:VisitaInteressado');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:VisitaInteressado');
    }

    public function replicate(AuthUser $authUser, VisitaInteressado $visitaInteressado): bool
    {
        return $authUser->can('Replicate:VisitaInteressado');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:VisitaInteressado');
    }
}

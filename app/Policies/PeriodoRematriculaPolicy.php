<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PeriodoRematricula;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PeriodoRematriculaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PeriodoRematricula');
    }

    public function view(AuthUser $authUser, PeriodoRematricula $periodoRematricula): bool
    {
        return $authUser->can('View:PeriodoRematricula');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PeriodoRematricula');
    }

    public function update(AuthUser $authUser, PeriodoRematricula $periodoRematricula): bool
    {
        return $authUser->can('Update:PeriodoRematricula');
    }

    public function delete(AuthUser $authUser, PeriodoRematricula $periodoRematricula): bool
    {
        return $authUser->can('Delete:PeriodoRematricula');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:PeriodoRematricula');
    }

    public function restore(AuthUser $authUser, PeriodoRematricula $periodoRematricula): bool
    {
        return $authUser->can('Restore:PeriodoRematricula');
    }

    public function forceDelete(AuthUser $authUser, PeriodoRematricula $periodoRematricula): bool
    {
        return $authUser->can('ForceDelete:PeriodoRematricula');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PeriodoRematricula');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PeriodoRematricula');
    }

    public function replicate(AuthUser $authUser, PeriodoRematricula $periodoRematricula): bool
    {
        return $authUser->can('Replicate:PeriodoRematricula');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PeriodoRematricula');
    }
}

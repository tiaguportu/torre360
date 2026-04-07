<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PeriodoLetivo;
use Illuminate\Auth\Access\HandlesAuthorization;

class PeriodoLetivoPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PeriodoLetivo');
    }

    public function view(AuthUser $authUser, PeriodoLetivo $periodoLetivo): bool
    {
        return $authUser->can('View:PeriodoLetivo');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PeriodoLetivo');
    }

    public function update(AuthUser $authUser, PeriodoLetivo $periodoLetivo): bool
    {
        return $authUser->can('Update:PeriodoLetivo');
    }

    public function delete(AuthUser $authUser, PeriodoLetivo $periodoLetivo): bool
    {
        return $authUser->can('Delete:PeriodoLetivo');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:PeriodoLetivo');
    }

    public function restore(AuthUser $authUser, PeriodoLetivo $periodoLetivo): bool
    {
        return $authUser->can('Restore:PeriodoLetivo');
    }

    public function forceDelete(AuthUser $authUser, PeriodoLetivo $periodoLetivo): bool
    {
        return $authUser->can('ForceDelete:PeriodoLetivo');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PeriodoLetivo');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PeriodoLetivo');
    }

    public function replicate(AuthUser $authUser, PeriodoLetivo $periodoLetivo): bool
    {
        return $authUser->can('Replicate:PeriodoLetivo');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PeriodoLetivo');
    }

}
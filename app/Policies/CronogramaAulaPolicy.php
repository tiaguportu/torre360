<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\CronogramaAula;
use Illuminate\Auth\Access\HandlesAuthorization;

class CronogramaAulaPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CronogramaAula');
    }

    public function view(AuthUser $authUser, CronogramaAula $cronogramaAula): bool
    {
        return $authUser->can('View:CronogramaAula');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CronogramaAula');
    }

    public function update(AuthUser $authUser, CronogramaAula $cronogramaAula): bool
    {
        return $authUser->can('Update:CronogramaAula');
    }

    public function delete(AuthUser $authUser, CronogramaAula $cronogramaAula): bool
    {
        return $authUser->can('Delete:CronogramaAula');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CronogramaAula');
    }

    public function restore(AuthUser $authUser, CronogramaAula $cronogramaAula): bool
    {
        return $authUser->can('Restore:CronogramaAula');
    }

    public function forceDelete(AuthUser $authUser, CronogramaAula $cronogramaAula): bool
    {
        return $authUser->can('ForceDelete:CronogramaAula');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CronogramaAula');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CronogramaAula');
    }

    public function replicate(AuthUser $authUser, CronogramaAula $cronogramaAula): bool
    {
        return $authUser->can('Replicate:CronogramaAula');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CronogramaAula');
    }

}
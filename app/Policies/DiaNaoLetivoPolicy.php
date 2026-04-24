<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DiaNaoLetivo;
use Illuminate\Auth\Access\HandlesAuthorization;

class DiaNaoLetivoPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DiaNaoLetivo');
    }

    public function view(AuthUser $authUser, DiaNaoLetivo $diaNaoLetivo): bool
    {
        return $authUser->can('View:DiaNaoLetivo');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DiaNaoLetivo');
    }

    public function update(AuthUser $authUser, DiaNaoLetivo $diaNaoLetivo): bool
    {
        return $authUser->can('Update:DiaNaoLetivo');
    }

    public function delete(AuthUser $authUser, DiaNaoLetivo $diaNaoLetivo): bool
    {
        return $authUser->can('Delete:DiaNaoLetivo');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:DiaNaoLetivo');
    }

    public function restore(AuthUser $authUser, DiaNaoLetivo $diaNaoLetivo): bool
    {
        return $authUser->can('Restore:DiaNaoLetivo');
    }

    public function forceDelete(AuthUser $authUser, DiaNaoLetivo $diaNaoLetivo): bool
    {
        return $authUser->can('ForceDelete:DiaNaoLetivo');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DiaNaoLetivo');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DiaNaoLetivo');
    }

    public function replicate(AuthUser $authUser, DiaNaoLetivo $diaNaoLetivo): bool
    {
        return $authUser->can('Replicate:DiaNaoLetivo');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DiaNaoLetivo');
    }

}
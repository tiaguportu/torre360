<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Contrato;
use Illuminate\Auth\Access\HandlesAuthorization;

class ContratoPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Contrato');
    }

    public function view(AuthUser $authUser, Contrato $contrato): bool
    {
        return $authUser->can('View:Contrato');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Contrato');
    }

    public function update(AuthUser $authUser, Contrato $contrato): bool
    {
        return $authUser->can('Update:Contrato');
    }

    public function delete(AuthUser $authUser, Contrato $contrato): bool
    {
        return $authUser->can('Delete:Contrato');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Contrato');
    }

    public function restore(AuthUser $authUser, Contrato $contrato): bool
    {
        return $authUser->can('Restore:Contrato');
    }

    public function forceDelete(AuthUser $authUser, Contrato $contrato): bool
    {
        return $authUser->can('ForceDelete:Contrato');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Contrato');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Contrato');
    }

    public function replicate(AuthUser $authUser, Contrato $contrato): bool
    {
        return $authUser->can('Replicate:Contrato');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Contrato');
    }

}
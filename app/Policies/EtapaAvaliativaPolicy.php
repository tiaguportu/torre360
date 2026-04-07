<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\EtapaAvaliativa;
use Illuminate\Auth\Access\HandlesAuthorization;

class EtapaAvaliativaPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:EtapaAvaliativa');
    }

    public function view(AuthUser $authUser, EtapaAvaliativa $etapaAvaliativa): bool
    {
        return $authUser->can('View:EtapaAvaliativa');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:EtapaAvaliativa');
    }

    public function update(AuthUser $authUser, EtapaAvaliativa $etapaAvaliativa): bool
    {
        return $authUser->can('Update:EtapaAvaliativa');
    }

    public function delete(AuthUser $authUser, EtapaAvaliativa $etapaAvaliativa): bool
    {
        return $authUser->can('Delete:EtapaAvaliativa');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:EtapaAvaliativa');
    }

    public function restore(AuthUser $authUser, EtapaAvaliativa $etapaAvaliativa): bool
    {
        return $authUser->can('Restore:EtapaAvaliativa');
    }

    public function forceDelete(AuthUser $authUser, EtapaAvaliativa $etapaAvaliativa): bool
    {
        return $authUser->can('ForceDelete:EtapaAvaliativa');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:EtapaAvaliativa');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:EtapaAvaliativa');
    }

    public function replicate(AuthUser $authUser, EtapaAvaliativa $etapaAvaliativa): bool
    {
        return $authUser->can('Replicate:EtapaAvaliativa');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:EtapaAvaliativa');
    }

}
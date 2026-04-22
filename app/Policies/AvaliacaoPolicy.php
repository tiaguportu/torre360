<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Avaliacao;
use Illuminate\Auth\Access\HandlesAuthorization;

class AvaliacaoPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Avaliacao');
    }

    public function view(AuthUser $authUser, Avaliacao $avaliacao): bool
    {
        return $authUser->can('View:Avaliacao');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Avaliacao');
    }

    public function update(AuthUser $authUser, Avaliacao $avaliacao): bool
    {
        return $authUser->can('Update:Avaliacao');
    }

    public function delete(AuthUser $authUser, Avaliacao $avaliacao): bool
    {
        return $authUser->can('Delete:Avaliacao');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Avaliacao');
    }

    public function restore(AuthUser $authUser, Avaliacao $avaliacao): bool
    {
        return $authUser->can('Restore:Avaliacao');
    }

    public function forceDelete(AuthUser $authUser, Avaliacao $avaliacao): bool
    {
        return $authUser->can('ForceDelete:Avaliacao');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Avaliacao');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Avaliacao');
    }

    public function replicate(AuthUser $authUser, Avaliacao $avaliacao): bool
    {
        return $authUser->can('Replicate:Avaliacao');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Avaliacao');
    }

    public function lancarNotas(AuthUser $authUser, Avaliacao $avaliacao): bool
    {
        return $authUser->can('LancarNotas:Avaliacao');
    }

}
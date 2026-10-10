<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:User');
    }

    public function view(AuthUser $authUser, ?User $targetUser = null): bool
    {
        if (! $authUser->can('View:User')) {
            return false;
        }

        // Se o usuário alvo for super_admin, apenas super_admin pode visualizá-lo
        if ($targetUser && $targetUser->hasRole('super_admin') && ! $authUser->hasRole('super_admin')) {
            return false;
        }

        return true;
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:User');
    }

    public function update(AuthUser $authUser, ?User $targetUser = null): bool
    {
        if (! $authUser->can('Update:User')) {
            return false;
        }

        // Contas de super_admin só podem ser editadas por quem já é super_admin (anti-privilege-escalation)
        if ($targetUser && $targetUser->hasRole('super_admin') && ! $authUser->hasRole('super_admin')) {
            return false;
        }

        return true;
    }

    public function delete(AuthUser $authUser, ?User $targetUser = null): bool
    {
        if (! $authUser->can('Delete:User')) {
            return false;
        }

        // Impede que um usuário exclua a sua própria conta
        if ($targetUser && $targetUser->id === $authUser->id) {
            return false;
        }

        // Apenas super_admin pode excluir outros super_admins
        if ($targetUser && $targetUser->hasRole('super_admin') && ! $authUser->hasRole('super_admin')) {
            return false;
        }

        return true;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:User');
    }

    public function restore(AuthUser $authUser, ?User $targetUser = null): bool
    {
        if (! $authUser->can('Restore:User')) {
            return false;
        }

        if ($targetUser && $targetUser->hasRole('super_admin') && ! $authUser->hasRole('super_admin')) {
            return false;
        }

        return true;
    }

    public function forceDelete(AuthUser $authUser, ?User $targetUser = null): bool
    {
        if (! $authUser->can('ForceDelete:User')) {
            return false;
        }

        // Impede auto-exclusão definitiva
        if ($targetUser && $targetUser->id === $authUser->id) {
            return false;
        }

        if ($targetUser && $targetUser->hasRole('super_admin') && ! $authUser->hasRole('super_admin')) {
            return false;
        }

        return true;
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:User');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:User');
    }

    public function replicate(AuthUser $authUser, ?User $targetUser = null): bool
    {
        if (! $authUser->can('Replicate:User')) {
            return false;
        }

        if ($targetUser && $targetUser->hasRole('super_admin') && ! $authUser->hasRole('super_admin')) {
            return false;
        }

        return true;
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:User');
    }
}

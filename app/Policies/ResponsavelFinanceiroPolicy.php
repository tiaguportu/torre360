<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ResponsavelFinanceiro;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ResponsavelFinanceiroPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ResponsavelFinanceiro');
    }

    public function view(AuthUser $authUser, ResponsavelFinanceiro $responsavelFinanceiro): bool
    {
        return $authUser->can('View:ResponsavelFinanceiro');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ResponsavelFinanceiro');
    }

    public function update(AuthUser $authUser, ResponsavelFinanceiro $responsavelFinanceiro): bool
    {
        return $authUser->can('Update:ResponsavelFinanceiro');
    }

    public function delete(AuthUser $authUser, ResponsavelFinanceiro $responsavelFinanceiro): bool
    {
        return $authUser->can('Delete:ResponsavelFinanceiro');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ResponsavelFinanceiro');
    }

    public function restore(AuthUser $authUser, ResponsavelFinanceiro $responsavelFinanceiro): bool
    {
        return $authUser->can('Restore:ResponsavelFinanceiro');
    }

    public function forceDelete(AuthUser $authUser, ResponsavelFinanceiro $responsavelFinanceiro): bool
    {
        return $authUser->can('ForceDelete:ResponsavelFinanceiro');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ResponsavelFinanceiro');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ResponsavelFinanceiro');
    }

    public function replicate(AuthUser $authUser, ResponsavelFinanceiro $responsavelFinanceiro): bool
    {
        return $authUser->can('Replicate:ResponsavelFinanceiro');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ResponsavelFinanceiro');
    }
}

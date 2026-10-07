<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ReguaCobranca;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ReguaCobrancaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ReguaCobranca');
    }

    public function view(AuthUser $authUser, ReguaCobranca $reguaCobranca): bool
    {
        return $authUser->can('View:ReguaCobranca');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ReguaCobranca');
    }

    public function update(AuthUser $authUser, ReguaCobranca $reguaCobranca): bool
    {
        return $authUser->can('Update:ReguaCobranca');
    }

    public function delete(AuthUser $authUser, ReguaCobranca $reguaCobranca): bool
    {
        return $authUser->can('Delete:ReguaCobranca');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ReguaCobranca');
    }

    public function restore(AuthUser $authUser, ReguaCobranca $reguaCobranca): bool
    {
        return $authUser->can('Restore:ReguaCobranca');
    }

    public function forceDelete(AuthUser $authUser, ReguaCobranca $reguaCobranca): bool
    {
        return $authUser->can('ForceDelete:ReguaCobranca');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ReguaCobranca');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ReguaCobranca');
    }

    public function replicate(AuthUser $authUser, ReguaCobranca $reguaCobranca): bool
    {
        return $authUser->can('Replicate:ReguaCobranca');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ReguaCobranca');
    }

    public function execute(AuthUser $authUser, ?ReguaCobranca $reguaCobranca = null): bool
    {
        return $authUser->can('Execute:ReguaCobranca');
    }
}

<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ReguaFollowUp;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ReguaFollowUpPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ReguaFollowUp');
    }

    public function view(AuthUser $authUser, ReguaFollowUp $reguaFollowUp): bool
    {
        return $authUser->can('View:ReguaFollowUp');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ReguaFollowUp');
    }

    public function update(AuthUser $authUser, ReguaFollowUp $reguaFollowUp): bool
    {
        return $authUser->can('Update:ReguaFollowUp');
    }

    public function delete(AuthUser $authUser, ReguaFollowUp $reguaFollowUp): bool
    {
        return $authUser->can('Delete:ReguaFollowUp');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ReguaFollowUp');
    }

    public function restore(AuthUser $authUser, ReguaFollowUp $reguaFollowUp): bool
    {
        return $authUser->can('Restore:ReguaFollowUp');
    }

    public function forceDelete(AuthUser $authUser, ReguaFollowUp $reguaFollowUp): bool
    {
        return $authUser->can('ForceDelete:ReguaFollowUp');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ReguaFollowUp');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ReguaFollowUp');
    }

    public function replicate(AuthUser $authUser, ReguaFollowUp $reguaFollowUp): bool
    {
        return $authUser->can('Replicate:ReguaFollowUp');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ReguaFollowUp');
    }

    public function execute(AuthUser $authUser, ?ReguaFollowUp $reguaFollowUp = null): bool
    {
        return $authUser->can('Execute:ReguaFollowUp');
    }
}

<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LandingLead;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class LandingLeadPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LandingLead');
    }

    public function view(AuthUser $authUser, LandingLead $landingLead): bool
    {
        return $authUser->can('View:LandingLead');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LandingLead');
    }

    public function update(AuthUser $authUser, LandingLead $landingLead): bool
    {
        return $authUser->can('Update:LandingLead');
    }

    public function delete(AuthUser $authUser, LandingLead $landingLead): bool
    {
        return $authUser->can('Delete:LandingLead');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:LandingLead');
    }

    public function restore(AuthUser $authUser, LandingLead $landingLead): bool
    {
        return $authUser->can('Restore:LandingLead');
    }

    public function forceDelete(AuthUser $authUser, LandingLead $landingLead): bool
    {
        return $authUser->can('ForceDelete:LandingLead');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:LandingLead');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:LandingLead');
    }

    public function replicate(AuthUser $authUser, LandingLead $landingLead): bool
    {
        return $authUser->can('Replicate:LandingLead');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:LandingLead');
    }
}

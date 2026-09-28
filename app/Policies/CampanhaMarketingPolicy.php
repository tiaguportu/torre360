<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CampanhaMarketing;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CampanhaMarketingPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CampanhaMarketing');
    }

    public function view(AuthUser $authUser, CampanhaMarketing $campanhaMarketing): bool
    {
        return $authUser->can('View:CampanhaMarketing');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CampanhaMarketing');
    }

    public function update(AuthUser $authUser, CampanhaMarketing $campanhaMarketing): bool
    {
        return $authUser->can('Update:CampanhaMarketing');
    }

    public function delete(AuthUser $authUser, CampanhaMarketing $campanhaMarketing): bool
    {
        return $authUser->can('Delete:CampanhaMarketing');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CampanhaMarketing');
    }

    public function restore(AuthUser $authUser, CampanhaMarketing $campanhaMarketing): bool
    {
        return $authUser->can('Restore:CampanhaMarketing');
    }

    public function forceDelete(AuthUser $authUser, CampanhaMarketing $campanhaMarketing): bool
    {
        return $authUser->can('ForceDelete:CampanhaMarketing');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CampanhaMarketing');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CampanhaMarketing');
    }

    public function replicate(AuthUser $authUser, CampanhaMarketing $campanhaMarketing): bool
    {
        return $authUser->can('Replicate:CampanhaMarketing');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CampanhaMarketing');
    }
}

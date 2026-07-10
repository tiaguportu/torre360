<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TemplateCrachaV3;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class TemplateCrachaV3Policy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TemplateCrachaV3');
    }

    public function view(AuthUser $authUser, TemplateCrachaV3 $templateCrachaV3): bool
    {
        return $authUser->can('View:TemplateCrachaV3');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TemplateCrachaV3');
    }

    public function update(AuthUser $authUser, TemplateCrachaV3 $templateCrachaV3): bool
    {
        return $authUser->can('Update:TemplateCrachaV3');
    }

    public function delete(AuthUser $authUser, TemplateCrachaV3 $templateCrachaV3): bool
    {
        return $authUser->can('Delete:TemplateCrachaV3');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:TemplateCrachaV3');
    }

    public function restore(AuthUser $authUser, TemplateCrachaV3 $templateCrachaV3): bool
    {
        return $authUser->can('Restore:TemplateCrachaV3');
    }

    public function forceDelete(AuthUser $authUser, TemplateCrachaV3 $templateCrachaV3): bool
    {
        return $authUser->can('ForceDelete:TemplateCrachaV3');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TemplateCrachaV3');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TemplateCrachaV3');
    }

    public function replicate(AuthUser $authUser, TemplateCrachaV3 $templateCrachaV3): bool
    {
        return $authUser->can('Replicate:TemplateCrachaV3');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:TemplateCrachaV3');
    }
}

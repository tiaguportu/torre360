<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TemplateCracha;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class TemplateCrachaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TemplateCracha');
    }

    public function view(AuthUser $authUser, TemplateCracha $templateCracha): bool
    {
        return $authUser->can('View:TemplateCracha');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TemplateCracha');
    }

    public function update(AuthUser $authUser, TemplateCracha $templateCracha): bool
    {
        return $authUser->can('Update:TemplateCracha');
    }

    public function delete(AuthUser $authUser, TemplateCracha $templateCracha): bool
    {
        return $authUser->can('Delete:TemplateCracha');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:TemplateCracha');
    }

    public function restore(AuthUser $authUser, TemplateCracha $templateCracha): bool
    {
        return $authUser->can('Restore:TemplateCracha');
    }

    public function forceDelete(AuthUser $authUser, TemplateCracha $templateCracha): bool
    {
        return $authUser->can('ForceDelete:TemplateCracha');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TemplateCracha');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TemplateCracha');
    }

    public function replicate(AuthUser $authUser, TemplateCracha $templateCracha): bool
    {
        return $authUser->can('Replicate:TemplateCracha');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:TemplateCracha');
    }
}

<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TemplateContrato;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class TemplateContratoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TemplateContrato');
    }

    public function view(AuthUser $authUser, TemplateContrato $templateContrato): bool
    {
        return $authUser->can('View:TemplateContrato');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TemplateContrato');
    }

    public function update(AuthUser $authUser, TemplateContrato $templateContrato): bool
    {
        return $authUser->can('Update:TemplateContrato');
    }

    public function delete(AuthUser $authUser, TemplateContrato $templateContrato): bool
    {
        return $authUser->can('Delete:TemplateContrato');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:TemplateContrato');
    }

    public function restore(AuthUser $authUser, TemplateContrato $templateContrato): bool
    {
        return $authUser->can('Restore:TemplateContrato');
    }

    public function forceDelete(AuthUser $authUser, TemplateContrato $templateContrato): bool
    {
        return $authUser->can('ForceDelete:TemplateContrato');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TemplateContrato');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TemplateContrato');
    }

    public function replicate(AuthUser $authUser, TemplateContrato $templateContrato): bool
    {
        return $authUser->can('Replicate:TemplateContrato');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:TemplateContrato');
    }
}

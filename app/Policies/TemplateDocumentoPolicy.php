<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TemplateDocumento;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class TemplateDocumentoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TemplateDocumento');
    }

    public function view(AuthUser $authUser, TemplateDocumento $templateDocumento): bool
    {
        return $authUser->can('View:TemplateDocumento');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TemplateDocumento');
    }

    public function update(AuthUser $authUser, TemplateDocumento $templateDocumento): bool
    {
        return $authUser->can('Update:TemplateDocumento');
    }

    public function delete(AuthUser $authUser, TemplateDocumento $templateDocumento): bool
    {
        return $authUser->can('Delete:TemplateDocumento');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:TemplateDocumento');
    }

    public function restore(AuthUser $authUser, TemplateDocumento $templateDocumento): bool
    {
        return $authUser->can('Restore:TemplateDocumento');
    }

    public function forceDelete(AuthUser $authUser, TemplateDocumento $templateDocumento): bool
    {
        return $authUser->can('ForceDelete:TemplateDocumento');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TemplateDocumento');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TemplateDocumento');
    }

    public function replicate(AuthUser $authUser, TemplateDocumento $templateDocumento): bool
    {
        return $authUser->can('Replicate:TemplateDocumento');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:TemplateDocumento');
    }
}

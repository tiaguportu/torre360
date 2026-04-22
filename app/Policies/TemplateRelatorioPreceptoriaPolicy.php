<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\TemplateRelatorioPreceptoria;
use Illuminate\Auth\Access\HandlesAuthorization;

class TemplateRelatorioPreceptoriaPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TemplateRelatorioPreceptoria');
    }

    public function view(AuthUser $authUser, TemplateRelatorioPreceptoria $templateRelatorioPreceptoria): bool
    {
        return $authUser->can('View:TemplateRelatorioPreceptoria');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TemplateRelatorioPreceptoria');
    }

    public function update(AuthUser $authUser, TemplateRelatorioPreceptoria $templateRelatorioPreceptoria): bool
    {
        return $authUser->can('Update:TemplateRelatorioPreceptoria');
    }

    public function delete(AuthUser $authUser, TemplateRelatorioPreceptoria $templateRelatorioPreceptoria): bool
    {
        return $authUser->can('Delete:TemplateRelatorioPreceptoria');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:TemplateRelatorioPreceptoria');
    }

    public function restore(AuthUser $authUser, TemplateRelatorioPreceptoria $templateRelatorioPreceptoria): bool
    {
        return $authUser->can('Restore:TemplateRelatorioPreceptoria');
    }

    public function forceDelete(AuthUser $authUser, TemplateRelatorioPreceptoria $templateRelatorioPreceptoria): bool
    {
        return $authUser->can('ForceDelete:TemplateRelatorioPreceptoria');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TemplateRelatorioPreceptoria');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TemplateRelatorioPreceptoria');
    }

    public function replicate(AuthUser $authUser, TemplateRelatorioPreceptoria $templateRelatorioPreceptoria): bool
    {
        return $authUser->can('Replicate:TemplateRelatorioPreceptoria');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:TemplateRelatorioPreceptoria');
    }

}
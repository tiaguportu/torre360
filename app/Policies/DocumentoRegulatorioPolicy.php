<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DocumentoRegulatorio;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class DocumentoRegulatorioPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DocumentoRegulatorio');
    }

    public function view(AuthUser $authUser, DocumentoRegulatorio $model): bool
    {
        return $authUser->can('View:DocumentoRegulatorio');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DocumentoRegulatorio');
    }

    public function update(AuthUser $authUser, DocumentoRegulatorio $model): bool
    {
        return $authUser->can('Update:DocumentoRegulatorio');
    }

    public function delete(AuthUser $authUser, DocumentoRegulatorio $model): bool
    {
        return $authUser->can('Delete:DocumentoRegulatorio');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:DocumentoRegulatorio');
    }

    public function restore(AuthUser $authUser, DocumentoRegulatorio $model): bool
    {
        return $authUser->can('Restore:DocumentoRegulatorio');
    }

    public function forceDelete(AuthUser $authUser, DocumentoRegulatorio $model): bool
    {
        return $authUser->can('ForceDelete:DocumentoRegulatorio');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DocumentoRegulatorio');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DocumentoRegulatorio');
    }

    public function replicate(AuthUser $authUser, DocumentoRegulatorio $model): bool
    {
        return $authUser->can('Replicate:DocumentoRegulatorio');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DocumentoRegulatorio');
    }
}

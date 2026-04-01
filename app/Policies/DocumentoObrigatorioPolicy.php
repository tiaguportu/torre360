<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DocumentoObrigatorio;
use Illuminate\Auth\Access\HandlesAuthorization;

class DocumentoObrigatorioPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DocumentoObrigatorio');
    }

    public function view(AuthUser $authUser, DocumentoObrigatorio $documentoObrigatorio): bool
    {
        return $authUser->can('View:DocumentoObrigatorio');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DocumentoObrigatorio');
    }

    public function update(AuthUser $authUser, DocumentoObrigatorio $documentoObrigatorio): bool
    {
        return $authUser->can('Update:DocumentoObrigatorio');
    }

    public function delete(AuthUser $authUser, DocumentoObrigatorio $documentoObrigatorio): bool
    {
        return $authUser->can('Delete:DocumentoObrigatorio');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:DocumentoObrigatorio');
    }

    public function restore(AuthUser $authUser, DocumentoObrigatorio $documentoObrigatorio): bool
    {
        return $authUser->can('Restore:DocumentoObrigatorio');
    }

    public function forceDelete(AuthUser $authUser, DocumentoObrigatorio $documentoObrigatorio): bool
    {
        return $authUser->can('ForceDelete:DocumentoObrigatorio');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DocumentoObrigatorio');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DocumentoObrigatorio');
    }

    public function replicate(AuthUser $authUser, DocumentoObrigatorio $documentoObrigatorio): bool
    {
        return $authUser->can('Replicate:DocumentoObrigatorio');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DocumentoObrigatorio');
    }

}
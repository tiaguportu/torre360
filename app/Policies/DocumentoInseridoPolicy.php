<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DocumentoInserido;
use Illuminate\Auth\Access\HandlesAuthorization;

class DocumentoInseridoPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DocumentoInserido');
    }

    public function view(AuthUser $authUser, DocumentoInserido $documentoInserido): bool
    {
        return $authUser->can('View:DocumentoInserido');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DocumentoInserido');
    }

    public function update(AuthUser $authUser, DocumentoInserido $documentoInserido): bool
    {
        return $authUser->can('Update:DocumentoInserido');
    }

    public function delete(AuthUser $authUser, DocumentoInserido $documentoInserido): bool
    {
        return $authUser->can('Delete:DocumentoInserido');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:DocumentoInserido');
    }

    public function restore(AuthUser $authUser, DocumentoInserido $documentoInserido): bool
    {
        return $authUser->can('Restore:DocumentoInserido');
    }

    public function forceDelete(AuthUser $authUser, DocumentoInserido $documentoInserido): bool
    {
        return $authUser->can('ForceDelete:DocumentoInserido');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DocumentoInserido');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DocumentoInserido');
    }

    public function replicate(AuthUser $authUser, DocumentoInserido $documentoInserido): bool
    {
        return $authUser->can('Replicate:DocumentoInserido');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DocumentoInserido');
    }

}
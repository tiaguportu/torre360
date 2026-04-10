<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\SituacaoDocumentoInserido;
use Illuminate\Auth\Access\HandlesAuthorization;

class SituacaoDocumentoInseridoPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SituacaoDocumentoInserido');
    }

    public function view(AuthUser $authUser, SituacaoDocumentoInserido $situacaoDocumentoInserido): bool
    {
        return $authUser->can('View:SituacaoDocumentoInserido');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SituacaoDocumentoInserido');
    }

    public function update(AuthUser $authUser, SituacaoDocumentoInserido $situacaoDocumentoInserido): bool
    {
        return $authUser->can('Update:SituacaoDocumentoInserido');
    }

    public function delete(AuthUser $authUser, SituacaoDocumentoInserido $situacaoDocumentoInserido): bool
    {
        return $authUser->can('Delete:SituacaoDocumentoInserido');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:SituacaoDocumentoInserido');
    }

    public function restore(AuthUser $authUser, SituacaoDocumentoInserido $situacaoDocumentoInserido): bool
    {
        return $authUser->can('Restore:SituacaoDocumentoInserido');
    }

    public function forceDelete(AuthUser $authUser, SituacaoDocumentoInserido $situacaoDocumentoInserido): bool
    {
        return $authUser->can('ForceDelete:SituacaoDocumentoInserido');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SituacaoDocumentoInserido');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SituacaoDocumentoInserido');
    }

    public function replicate(AuthUser $authUser, SituacaoDocumentoInserido $situacaoDocumentoInserido): bool
    {
        return $authUser->can('Replicate:SituacaoDocumentoInserido');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SituacaoDocumentoInserido');
    }

}
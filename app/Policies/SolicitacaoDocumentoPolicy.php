<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SolicitacaoDocumento;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class SolicitacaoDocumentoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SolicitacaoDocumento');
    }

    public function view(AuthUser $authUser, SolicitacaoDocumento $solicitacaoDocumento): bool
    {
        return $authUser->can('View:SolicitacaoDocumento');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SolicitacaoDocumento');
    }

    public function update(AuthUser $authUser, SolicitacaoDocumento $solicitacaoDocumento): bool
    {
        return $authUser->can('Update:SolicitacaoDocumento');
    }

    public function delete(AuthUser $authUser, SolicitacaoDocumento $solicitacaoDocumento): bool
    {
        return $authUser->can('Delete:SolicitacaoDocumento');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:SolicitacaoDocumento');
    }

    public function restore(AuthUser $authUser, SolicitacaoDocumento $solicitacaoDocumento): bool
    {
        return $authUser->can('Restore:SolicitacaoDocumento');
    }

    public function forceDelete(AuthUser $authUser, SolicitacaoDocumento $solicitacaoDocumento): bool
    {
        return $authUser->can('ForceDelete:SolicitacaoDocumento');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SolicitacaoDocumento');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SolicitacaoDocumento');
    }

    public function replicate(AuthUser $authUser, SolicitacaoDocumento $solicitacaoDocumento): bool
    {
        return $authUser->can('Replicate:SolicitacaoDocumento');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SolicitacaoDocumento');
    }
}

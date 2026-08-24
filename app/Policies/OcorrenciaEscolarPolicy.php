<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OcorrenciaEscolar;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class OcorrenciaEscolarPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:OcorrenciaEscolar');
    }

    public function view(AuthUser $authUser, OcorrenciaEscolar $ocorrenciaEscolar): bool
    {
        return $authUser->can('View:OcorrenciaEscolar');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:OcorrenciaEscolar');
    }

    public function update(AuthUser $authUser, OcorrenciaEscolar $ocorrenciaEscolar): bool
    {
        return $authUser->can('Update:OcorrenciaEscolar');
    }

    public function delete(AuthUser $authUser, OcorrenciaEscolar $ocorrenciaEscolar): bool
    {
        return $authUser->can('Delete:OcorrenciaEscolar');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:OcorrenciaEscolar');
    }

    public function restore(AuthUser $authUser, OcorrenciaEscolar $ocorrenciaEscolar): bool
    {
        return $authUser->can('Restore:OcorrenciaEscolar');
    }

    public function forceDelete(AuthUser $authUser, OcorrenciaEscolar $ocorrenciaEscolar): bool
    {
        return $authUser->can('ForceDelete:OcorrenciaEscolar');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:OcorrenciaEscolar');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:OcorrenciaEscolar');
    }

    public function replicate(AuthUser $authUser, OcorrenciaEscolar $ocorrenciaEscolar): bool
    {
        return $authUser->can('Replicate:OcorrenciaEscolar');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:OcorrenciaEscolar');
    }

    public function reenviarNotificacao(AuthUser $authUser, OcorrenciaEscolar $ocorrenciaEscolar): bool
    {
        return $authUser->can('ReenviarNotificacao:OcorrenciaEscolar');
    }
}

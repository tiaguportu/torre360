<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\FrequenciaEscolar;
use Illuminate\Auth\Access\HandlesAuthorization;

class FrequenciaEscolarPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:FrequenciaEscolar');
    }

    public function view(AuthUser $authUser, FrequenciaEscolar $frequenciaEscolar): bool
    {
        return $authUser->can('View:FrequenciaEscolar');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:FrequenciaEscolar');
    }

    public function update(AuthUser $authUser, FrequenciaEscolar $frequenciaEscolar): bool
    {
        return $authUser->can('Update:FrequenciaEscolar');
    }

    public function delete(AuthUser $authUser, FrequenciaEscolar $frequenciaEscolar): bool
    {
        return $authUser->can('Delete:FrequenciaEscolar');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:FrequenciaEscolar');
    }

    public function restore(AuthUser $authUser, FrequenciaEscolar $frequenciaEscolar): bool
    {
        return $authUser->can('Restore:FrequenciaEscolar');
    }

    public function forceDelete(AuthUser $authUser, FrequenciaEscolar $frequenciaEscolar): bool
    {
        return $authUser->can('ForceDelete:FrequenciaEscolar');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:FrequenciaEscolar');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:FrequenciaEscolar');
    }

    public function replicate(AuthUser $authUser, FrequenciaEscolar $frequenciaEscolar): bool
    {
        return $authUser->can('Replicate:FrequenciaEscolar');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:FrequenciaEscolar');
    }

}
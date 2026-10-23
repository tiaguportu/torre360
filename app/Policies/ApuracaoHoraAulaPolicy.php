<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ApuracaoHoraAula;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ApuracaoHoraAulaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ApuracaoHoraAula');
    }

    public function view(AuthUser $authUser, ApuracaoHoraAula $model): bool
    {
        return $authUser->can('View:ApuracaoHoraAula');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ApuracaoHoraAula');
    }

    public function update(AuthUser $authUser, ApuracaoHoraAula $model): bool
    {
        return $authUser->can('Update:ApuracaoHoraAula');
    }

    public function delete(AuthUser $authUser, ApuracaoHoraAula $model): bool
    {
        return $authUser->can('Delete:ApuracaoHoraAula');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ApuracaoHoraAula');
    }

    public function restore(AuthUser $authUser, ApuracaoHoraAula $model): bool
    {
        return $authUser->can('Restore:ApuracaoHoraAula');
    }

    public function forceDelete(AuthUser $authUser, ApuracaoHoraAula $model): bool
    {
        return $authUser->can('ForceDelete:ApuracaoHoraAula');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ApuracaoHoraAula');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ApuracaoHoraAula');
    }

    public function replicate(AuthUser $authUser, ApuracaoHoraAula $model): bool
    {
        return $authUser->can('Replicate:ApuracaoHoraAula');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ApuracaoHoraAula');
    }
}

<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\IndicacaoInteressado;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class IndicacaoInteressadoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:IndicacaoInteressado');
    }

    public function view(AuthUser $authUser, IndicacaoInteressado $indicacaoInteressado): bool
    {
        return $authUser->can('View:IndicacaoInteressado');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:IndicacaoInteressado');
    }

    public function update(AuthUser $authUser, IndicacaoInteressado $indicacaoInteressado): bool
    {
        return $authUser->can('Update:IndicacaoInteressado');
    }

    public function delete(AuthUser $authUser, IndicacaoInteressado $indicacaoInteressado): bool
    {
        return $authUser->can('Delete:IndicacaoInteressado');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:IndicacaoInteressado');
    }

    public function restore(AuthUser $authUser, IndicacaoInteressado $indicacaoInteressado): bool
    {
        return $authUser->can('Restore:IndicacaoInteressado');
    }

    public function forceDelete(AuthUser $authUser, IndicacaoInteressado $indicacaoInteressado): bool
    {
        return $authUser->can('ForceDelete:IndicacaoInteressado');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:IndicacaoInteressado');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:IndicacaoInteressado');
    }

    public function replicate(AuthUser $authUser, IndicacaoInteressado $indicacaoInteressado): bool
    {
        return $authUser->can('Replicate:IndicacaoInteressado');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:IndicacaoInteressado');
    }
}

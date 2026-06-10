<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AvaliacaoHabilidade;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class AvaliacaoHabilidadePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AvaliacaoHabilidade');
    }

    public function view(AuthUser $authUser, AvaliacaoHabilidade $avaliacaoHabilidade): bool
    {
        return $authUser->can('View:AvaliacaoHabilidade');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AvaliacaoHabilidade');
    }

    public function update(AuthUser $authUser, AvaliacaoHabilidade $avaliacaoHabilidade): bool
    {
        return $authUser->can('Update:AvaliacaoHabilidade');
    }

    public function delete(AuthUser $authUser, AvaliacaoHabilidade $avaliacaoHabilidade): bool
    {
        return $authUser->can('Delete:AvaliacaoHabilidade');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:AvaliacaoHabilidade');
    }

    public function restore(AuthUser $authUser, AvaliacaoHabilidade $avaliacaoHabilidade): bool
    {
        return $authUser->can('Restore:AvaliacaoHabilidade');
    }

    public function forceDelete(AuthUser $authUser, AvaliacaoHabilidade $avaliacaoHabilidade): bool
    {
        return $authUser->can('ForceDelete:AvaliacaoHabilidade');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AvaliacaoHabilidade');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AvaliacaoHabilidade');
    }

    public function replicate(AuthUser $authUser, AvaliacaoHabilidade $avaliacaoHabilidade): bool
    {
        return $authUser->can('Replicate:AvaliacaoHabilidade');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AvaliacaoHabilidade');
    }
}

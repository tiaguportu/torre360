<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\NotaHabilidade;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class NotaHabilidadePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:NotaHabilidade');
    }

    public function view(AuthUser $authUser, NotaHabilidade $notaHabilidade): bool
    {
        return $authUser->can('View:NotaHabilidade');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:NotaHabilidade');
    }

    public function update(AuthUser $authUser, NotaHabilidade $notaHabilidade): bool
    {
        return $authUser->can('Update:NotaHabilidade');
    }

    public function delete(AuthUser $authUser, NotaHabilidade $notaHabilidade): bool
    {
        return $authUser->can('Delete:NotaHabilidade');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:NotaHabilidade');
    }

    public function restore(AuthUser $authUser, NotaHabilidade $notaHabilidade): bool
    {
        return $authUser->can('Restore:NotaHabilidade');
    }

    public function forceDelete(AuthUser $authUser, NotaHabilidade $notaHabilidade): bool
    {
        return $authUser->can('ForceDelete:NotaHabilidade');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:NotaHabilidade');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:NotaHabilidade');
    }

    public function replicate(AuthUser $authUser, NotaHabilidade $notaHabilidade): bool
    {
        return $authUser->can('Replicate:NotaHabilidade');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:NotaHabilidade');
    }
}

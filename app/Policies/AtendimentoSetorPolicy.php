<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AtendimentoSetor;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class AtendimentoSetorPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AtendimentoSetor');
    }

    public function view(AuthUser $authUser, AtendimentoSetor $atendimentoSetor): bool
    {
        return $authUser->can('View:AtendimentoSetor');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AtendimentoSetor');
    }

    public function update(AuthUser $authUser, AtendimentoSetor $atendimentoSetor): bool
    {
        return $authUser->can('Update:AtendimentoSetor');
    }

    public function delete(AuthUser $authUser, AtendimentoSetor $atendimentoSetor): bool
    {
        return $authUser->can('Delete:AtendimentoSetor');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:AtendimentoSetor');
    }

    public function restore(AuthUser $authUser, AtendimentoSetor $atendimentoSetor): bool
    {
        return $authUser->can('Restore:AtendimentoSetor');
    }

    public function forceDelete(AuthUser $authUser, AtendimentoSetor $atendimentoSetor): bool
    {
        return $authUser->can('ForceDelete:AtendimentoSetor');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AtendimentoSetor');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AtendimentoSetor');
    }

    public function replicate(AuthUser $authUser, AtendimentoSetor $atendimentoSetor): bool
    {
        return $authUser->can('Replicate:AtendimentoSetor');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AtendimentoSetor');
    }
}

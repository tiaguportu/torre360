<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AtendimentoEnfermagem;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class AtendimentoEnfermagemPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AtendimentoEnfermagem');
    }

    public function view(AuthUser $authUser, AtendimentoEnfermagem $atendimentoEnfermagem): bool
    {
        return $authUser->can('View:AtendimentoEnfermagem');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AtendimentoEnfermagem');
    }

    public function update(AuthUser $authUser, AtendimentoEnfermagem $atendimentoEnfermagem): bool
    {
        return $authUser->can('Update:AtendimentoEnfermagem');
    }

    public function delete(AuthUser $authUser, AtendimentoEnfermagem $atendimentoEnfermagem): bool
    {
        return $authUser->can('Delete:AtendimentoEnfermagem');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:AtendimentoEnfermagem');
    }

    public function restore(AuthUser $authUser, AtendimentoEnfermagem $atendimentoEnfermagem): bool
    {
        return $authUser->can('Restore:AtendimentoEnfermagem');
    }

    public function forceDelete(AuthUser $authUser, AtendimentoEnfermagem $atendimentoEnfermagem): bool
    {
        return $authUser->can('ForceDelete:AtendimentoEnfermagem');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AtendimentoEnfermagem');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AtendimentoEnfermagem');
    }

    public function replicate(AuthUser $authUser, AtendimentoEnfermagem $atendimentoEnfermagem): bool
    {
        return $authUser->can('Replicate:AtendimentoEnfermagem');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AtendimentoEnfermagem');
    }
}

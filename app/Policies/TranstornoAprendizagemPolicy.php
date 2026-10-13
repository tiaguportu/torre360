<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TranstornoAprendizagem;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class TranstornoAprendizagemPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TranstornoAprendizagem');
    }

    public function view(AuthUser $authUser, TranstornoAprendizagem $record): bool
    {
        return $authUser->can('View:TranstornoAprendizagem');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TranstornoAprendizagem');
    }

    public function update(AuthUser $authUser, TranstornoAprendizagem $record): bool
    {
        return $authUser->can('Update:TranstornoAprendizagem');
    }

    public function delete(AuthUser $authUser, TranstornoAprendizagem $record): bool
    {
        return $authUser->can('Delete:TranstornoAprendizagem');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:TranstornoAprendizagem');
    }

    public function restore(AuthUser $authUser, TranstornoAprendizagem $record): bool
    {
        return $authUser->can('Restore:TranstornoAprendizagem');
    }

    public function forceDelete(AuthUser $authUser, TranstornoAprendizagem $record): bool
    {
        return $authUser->can('ForceDelete:TranstornoAprendizagem');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TranstornoAprendizagem');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TranstornoAprendizagem');
    }
}

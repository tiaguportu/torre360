<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Objecao;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ObjecaoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Objecao');
    }

    public function view(AuthUser $authUser, Objecao $objecao): bool
    {
        return $authUser->can('View:Objecao');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Objecao');
    }

    public function update(AuthUser $authUser, Objecao $objecao): bool
    {
        return $authUser->can('Update:Objecao');
    }

    public function delete(AuthUser $authUser, Objecao $objecao): bool
    {
        return $authUser->can('Delete:Objecao');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Objecao');
    }

    public function restore(AuthUser $authUser, Objecao $objecao): bool
    {
        return $authUser->can('Restore:Objecao');
    }

    public function forceDelete(AuthUser $authUser, Objecao $objecao): bool
    {
        return $authUser->can('ForceDelete:Objecao');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Objecao');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Objecao');
    }

    public function replicate(AuthUser $authUser, Objecao $objecao): bool
    {
        return $authUser->can('Replicate:Objecao');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Objecao');
    }
}

<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MaterialAula;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class MaterialAulaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MaterialAula');
    }

    public function view(AuthUser $authUser, MaterialAula $materialAula): bool
    {
        return $authUser->can('View:MaterialAula');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MaterialAula');
    }

    public function update(AuthUser $authUser, MaterialAula $materialAula): bool
    {
        return $authUser->can('Update:MaterialAula');
    }

    public function delete(AuthUser $authUser, MaterialAula $materialAula): bool
    {
        return $authUser->can('Delete:MaterialAula');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:MaterialAula');
    }
}

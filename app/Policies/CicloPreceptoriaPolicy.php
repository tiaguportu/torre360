<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CicloPreceptoria;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CicloPreceptoriaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CicloPreceptoria');
    }

    public function view(AuthUser $authUser, CicloPreceptoria $record): bool
    {
        return $authUser->can('View:CicloPreceptoria');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CicloPreceptoria');
    }

    public function update(AuthUser $authUser, CicloPreceptoria $record): bool
    {
        return $authUser->can('Update:CicloPreceptoria');
    }

    public function delete(AuthUser $authUser, CicloPreceptoria $record): bool
    {
        return $authUser->can('Delete:CicloPreceptoria');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CicloPreceptoria');
    }
}

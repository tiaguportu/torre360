<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BolsaConcedida;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class BolsaConcedidaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:BolsaConcedida');
    }

    public function view(AuthUser $authUser, BolsaConcedida $bolsaConcedida): bool
    {
        return $authUser->can('View:BolsaConcedida');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:BolsaConcedida');
    }

    public function update(AuthUser $authUser, BolsaConcedida $bolsaConcedida): bool
    {
        return $authUser->can('Update:BolsaConcedida');
    }

    public function delete(AuthUser $authUser, BolsaConcedida $bolsaConcedida): bool
    {
        return $authUser->can('Delete:BolsaConcedida');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:BolsaConcedida');
    }
}

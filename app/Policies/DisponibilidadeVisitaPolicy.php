<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DisponibilidadeVisita;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class DisponibilidadeVisitaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DisponibilidadeVisita');
    }

    public function view(AuthUser $authUser, DisponibilidadeVisita $disponibilidade): bool
    {
        return $authUser->can('View:DisponibilidadeVisita');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DisponibilidadeVisita');
    }

    public function update(AuthUser $authUser, DisponibilidadeVisita $disponibilidade): bool
    {
        return $authUser->can('Update:DisponibilidadeVisita');
    }

    public function delete(AuthUser $authUser, DisponibilidadeVisita $disponibilidade): bool
    {
        return $authUser->can('Delete:DisponibilidadeVisita');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:DisponibilidadeVisita');
    }
}

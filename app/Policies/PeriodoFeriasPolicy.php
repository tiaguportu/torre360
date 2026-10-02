<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PeriodoFerias;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PeriodoFeriasPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PeriodoFerias');
    }

    public function view(AuthUser $authUser, PeriodoFerias $periodoFerias): bool
    {
        return $authUser->can('View:PeriodoFerias');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PeriodoFerias');
    }

    public function update(AuthUser $authUser, PeriodoFerias $periodoFerias): bool
    {
        return $authUser->can('Update:PeriodoFerias');
    }

    public function delete(AuthUser $authUser, PeriodoFerias $periodoFerias): bool
    {
        return $authUser->can('Delete:PeriodoFerias');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:PeriodoFerias');
    }
}

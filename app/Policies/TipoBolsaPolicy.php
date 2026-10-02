<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TipoBolsa;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class TipoBolsaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TipoBolsa');
    }

    public function view(AuthUser $authUser, TipoBolsa $tipoBolsa): bool
    {
        return $authUser->can('View:TipoBolsa');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TipoBolsa');
    }

    public function update(AuthUser $authUser, TipoBolsa $tipoBolsa): bool
    {
        return $authUser->can('Update:TipoBolsa');
    }

    public function delete(AuthUser $authUser, TipoBolsa $tipoBolsa): bool
    {
        return $authUser->can('Delete:TipoBolsa');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:TipoBolsa');
    }
}

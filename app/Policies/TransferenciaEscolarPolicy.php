<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TransferenciaEscolar;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class TransferenciaEscolarPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TransferenciaEscolar');
    }

    public function view(AuthUser $authUser, TransferenciaEscolar $transferenciaEscolar): bool
    {
        return $authUser->can('View:TransferenciaEscolar');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TransferenciaEscolar');
    }

    public function update(AuthUser $authUser, TransferenciaEscolar $transferenciaEscolar): bool
    {
        return $authUser->can('Update:TransferenciaEscolar');
    }

    public function delete(AuthUser $authUser, TransferenciaEscolar $transferenciaEscolar): bool
    {
        return $authUser->can('Delete:TransferenciaEscolar');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:TransferenciaEscolar');
    }
}

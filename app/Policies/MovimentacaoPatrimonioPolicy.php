<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MovimentacaoPatrimonio;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class MovimentacaoPatrimonioPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:BemPatrimonial');
    }

    public function view(AuthUser $authUser, MovimentacaoPatrimonio $movimentacaoPatrimonio): bool
    {
        return $authUser->can('View:BemPatrimonial');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Update:BemPatrimonial');
    }

    public function update(AuthUser $authUser, MovimentacaoPatrimonio $movimentacaoPatrimonio): bool
    {
        return $authUser->can('Update:BemPatrimonial');
    }

    public function delete(AuthUser $authUser, MovimentacaoPatrimonio $movimentacaoPatrimonio): bool
    {
        return $authUser->can('Delete:BemPatrimonial');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:BemPatrimonial');
    }
}

<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\AnotacaoOs;
use Illuminate\Auth\Access\HandlesAuthorization;

class AnotacaoOsPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_anotacao::os');
    }

    public function view(AuthUser $authUser, AnotacaoOs $anotacaoOs): bool
    {
        return $authUser->can('view_anotacao::os');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_anotacao::os');
    }

    public function update(AuthUser $authUser, AnotacaoOs $anotacaoOs): bool
    {
        return $authUser->can('update_anotacao::os');
    }

    public function delete(AuthUser $authUser, AnotacaoOs $anotacaoOs): bool
    {
        return $authUser->can('delete_anotacao::os');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete_any_anotacao::os');
    }
}

<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PropostaComercial;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PropostaComercialPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PropostaComercial');
    }

    public function view(AuthUser $authUser, PropostaComercial $proposta): bool
    {
        return $authUser->can('View:PropostaComercial');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PropostaComercial');
    }

    public function update(AuthUser $authUser, PropostaComercial $proposta): bool
    {
        return $authUser->can('Update:PropostaComercial');
    }

    public function delete(AuthUser $authUser, PropostaComercial $proposta): bool
    {
        return $authUser->can('Delete:PropostaComercial');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:PropostaComercial');
    }

    public function aprovar(AuthUser $authUser, PropostaComercial $proposta): bool
    {
        return $authUser->can('Aprovar:PropostaComercial') || $proposta->podeSerAprovadaPor($authUser);
    }
}

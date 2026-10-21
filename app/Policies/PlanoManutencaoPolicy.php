<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PlanoManutencao;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PlanoManutencaoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PlanoManutencao');
    }

    public function view(AuthUser $authUser, PlanoManutencao $plano): bool
    {
        return $authUser->can('View:PlanoManutencao');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PlanoManutencao');
    }

    public function update(AuthUser $authUser, PlanoManutencao $plano): bool
    {
        return $authUser->can('Update:PlanoManutencao');
    }

    public function delete(AuthUser $authUser, PlanoManutencao $plano): bool
    {
        return $authUser->can('Delete:PlanoManutencao');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:PlanoManutencao');
    }

    public function gerarOrdem(AuthUser $authUser, PlanoManutencao $plano): bool
    {
        return $authUser->can('GerarOrdem:PlanoManutencao') && $plano->ativo;
    }

    public function registrarExecucao(AuthUser $authUser, PlanoManutencao $plano): bool
    {
        return $authUser->can('RegistrarExecucao:PlanoManutencao') && $plano->ativo;
    }
}

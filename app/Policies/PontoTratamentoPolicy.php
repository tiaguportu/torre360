<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StatusTratamentoPonto;
use App\Models\PontoTratamento;
use App\Models\User;
use App\Support\FuncionarioDoUsuario;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Cada trabalhador pede e acompanha os próprios ajustes; a administração vê todos e decide. Quem pediu não aprova o
 * próprio pedido (a regra definitiva está no serviço). Ajuste decidido não se altera nem se apaga.
 */
class PontoTratamentoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PontoTratamento');
    }

    public function view(AuthUser $authUser, PontoTratamento $tratamento): bool
    {
        /** @var User $authUser */
        $tratamento->loadMissing('funcionario');

        return $authUser->can('View:PontoTratamento')
            && (FuncionarioDoUsuario::ehOProprio($authUser, $tratamento->funcionario) || $authUser->can('Aprovar:PontoTratamento'));
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PontoTratamento');
    }

    public function aprovar(AuthUser $authUser, PontoTratamento $tratamento): bool
    {
        return $authUser->can('Aprovar:PontoTratamento')
            && $tratamento->status === StatusTratamentoPonto::Pendente
            && $tratamento->solicitado_por_user_id !== $authUser->getAuthIdentifier();
    }

    public function recusar(AuthUser $authUser, PontoTratamento $tratamento): bool
    {
        return $authUser->can('Aprovar:PontoTratamento') && $tratamento->status === StatusTratamentoPonto::Pendente;
    }

    public function update(AuthUser $authUser, PontoTratamento $tratamento): bool
    {
        return false;
    }

    public function delete(AuthUser $authUser, PontoTratamento $tratamento): bool
    {
        return false;
    }
}

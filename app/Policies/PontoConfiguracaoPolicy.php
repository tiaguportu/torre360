<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PontoConfiguracao;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Configurar o ponto (modo, requisitos legais, tolerâncias) é decisão da administração. A configuração guarda o
 * sequenciador dos registros e por isso nunca é apagada.
 */
class PontoConfiguracaoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PontoConfiguracao');
    }

    public function view(AuthUser $authUser, PontoConfiguracao $config): bool
    {
        return $authUser->can('View:PontoConfiguracao');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PontoConfiguracao');
    }

    public function update(AuthUser $authUser, PontoConfiguracao $config): bool
    {
        return $authUser->can('Update:PontoConfiguracao');
    }

    public function delete(AuthUser $authUser, PontoConfiguracao $config): bool
    {
        return false;
    }
}

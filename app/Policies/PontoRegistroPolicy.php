<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Funcionario;
use App\Models\PontoRegistro;
use App\Models\User;
use App\Support\FuncionarioDoUsuario;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * O ponto é dado pessoal do trabalhador: cada um enxerga os próprios registros; a administração enxerga os de todos
 * (e cada consulta a dados de outra pessoa é registrada pela tela que a faz). Registro nunca é criado à mão, alterado
 * ou apagado: nascer é só pelo serviço de registro, e as demais habilidades ficam sempre negadas.
 */
class PontoRegistroPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PontoRegistro');
    }

    public function view(AuthUser $authUser, PontoRegistro $registro): bool
    {
        /** @var User $authUser */
        $registro->loadMissing('funcionario');

        return FuncionarioDoUsuario::ehOProprio($authUser, $registro->funcionario) || $authUser->can('View:PontoRegistro');
    }

    /**
     * Pode marcar o ponto: tem a permissão e tem cadastro de funcionário ativo.
     */
    public function registrar(AuthUser $authUser): bool
    {
        /** @var User $authUser */
        return $authUser->can('Registrar:PontoRegistro') && FuncionarioDoUsuario::para($authUser) !== null;
    }

    public function verEspelhoDeTodos(AuthUser $authUser): bool
    {
        return $authUser->can('VerEspelhoDeTodos:PontoRegistro');
    }

    public function verEspelho(AuthUser $authUser, Funcionario $funcionario): bool
    {
        /** @var User $authUser */
        return FuncionarioDoUsuario::ehOProprio($authUser, $funcionario) || $this->verEspelhoDeTodos($authUser);
    }

    public function create(AuthUser $authUser): bool
    {
        return false;
    }

    public function update(AuthUser $authUser, PontoRegistro $registro): bool
    {
        return false;
    }

    public function delete(AuthUser $authUser, PontoRegistro $registro): bool
    {
        return false;
    }
}

<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StatusPlanoDesenvolvimento;
use App\Models\PlanoDesenvolvimentoDocente;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Plano de desenvolvimento é dado sensível de funcionário. A coordenação e a administração conduzem; o professor só
 * enxerga o próprio plano depois de ativado, dá ciência e registra o andamento das ações. Quem é o dono do plano
 * não o edita nem o conclui.
 */
class PlanoDesenvolvimentoDocentePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PlanoDesenvolvimentoDocente');
    }

    public function view(AuthUser $authUser, PlanoDesenvolvimentoDocente $plano): bool
    {
        /** @var User $authUser */
        $plano->loadMissing('funcionario');

        if (! $authUser->can('View:PlanoDesenvolvimentoDocente')) {
            return false;
        }

        return $authUser->atuaComoProfessor()
            ? $plano->pertenceA($authUser) && $plano->status !== StatusPlanoDesenvolvimento::Rascunho
            : true;
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PlanoDesenvolvimentoDocente');
    }

    public function update(AuthUser $authUser, PlanoDesenvolvimentoDocente $plano): bool
    {
        /** @var User $authUser */
        $plano->loadMissing('funcionario');

        return $authUser->can('Update:PlanoDesenvolvimentoDocente')
            && $plano->status !== StatusPlanoDesenvolvimento::Concluido
            && ! $plano->pertenceA($authUser);
    }

    public function ativar(AuthUser $authUser, PlanoDesenvolvimentoDocente $plano): bool
    {
        return $authUser->can('Ativar:PlanoDesenvolvimentoDocente')
            && $plano->status === StatusPlanoDesenvolvimento::Rascunho
            && $this->update($authUser, $plano);
    }

    public function concluir(AuthUser $authUser, PlanoDesenvolvimentoDocente $plano): bool
    {
        return $authUser->can('Concluir:PlanoDesenvolvimentoDocente')
            && $plano->status === StatusPlanoDesenvolvimento::Ativo
            && $this->update($authUser, $plano);
    }

    public function darCiencia(AuthUser $authUser, PlanoDesenvolvimentoDocente $plano): bool
    {
        /** @var User $authUser */
        $plano->loadMissing('funcionario');

        return $authUser->can('DarCiencia:PlanoDesenvolvimentoDocente')
            && $plano->status === StatusPlanoDesenvolvimento::Ativo
            && $plano->ciencia_em === null
            && $plano->pertenceA($authUser);
    }

    public function atualizarAcao(AuthUser $authUser, PlanoDesenvolvimentoDocente $plano): bool
    {
        /** @var User $authUser */
        $plano->loadMissing('funcionario');

        return $authUser->can('AtualizarAcao:PlanoDesenvolvimentoDocente')
            && $plano->status === StatusPlanoDesenvolvimento::Ativo
            && ($plano->pertenceA($authUser) || $authUser->can('Update:PlanoDesenvolvimentoDocente'));
    }

    public function delete(AuthUser $authUser, PlanoDesenvolvimentoDocente $plano): bool
    {
        return $authUser->can('Delete:PlanoDesenvolvimentoDocente') && $plano->status === StatusPlanoDesenvolvimento::Rascunho;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:PlanoDesenvolvimentoDocente');
    }
}

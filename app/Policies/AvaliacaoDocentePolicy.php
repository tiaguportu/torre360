<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AvaliacaoDocente;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Avaliação de desempenho é dado sensível de funcionário. Quem atua como professor só enxerga as próprias avaliações
 * já finalizadas (`AvaliacaoDocente::visiveisPara`); coordenação e administração veem todas. Só o avaliador altera o
 * próprio rascunho, e uma avaliação finalizada é imutável até alguém com `Reabrir` devolvê-la a rascunho.
 */
class AvaliacaoDocentePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AvaliacaoDocente');
    }

    public function view(AuthUser $authUser, AvaliacaoDocente $avaliacao): bool
    {
        /** @var User $authUser */
        return $authUser->can('View:AvaliacaoDocente') && $avaliacao->visivelPara($authUser);
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AvaliacaoDocente');
    }

    public function update(AuthUser $authUser, AvaliacaoDocente $avaliacao): bool
    {
        return $authUser->can('Update:AvaliacaoDocente')
            && ! $avaliacao->estaFinalizada()
            && $this->ehAvaliadorOuAdministrador($authUser, $avaliacao);
    }

    public function delete(AuthUser $authUser, AvaliacaoDocente $avaliacao): bool
    {
        return $authUser->can('Delete:AvaliacaoDocente')
            && (! $avaliacao->estaFinalizada() || $authUser->can('Reabrir:AvaliacaoDocente'));
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:AvaliacaoDocente');
    }

    public function finalizar(AuthUser $authUser, AvaliacaoDocente $avaliacao): bool
    {
        return $authUser->can('Finalizar:AvaliacaoDocente')
            && ! $avaliacao->estaFinalizada()
            && $this->ehAvaliadorOuAdministrador($authUser, $avaliacao);
    }

    public function reabrir(AuthUser $authUser, AvaliacaoDocente $avaliacao): bool
    {
        return $authUser->can('Reabrir:AvaliacaoDocente') && $avaliacao->estaFinalizada();
    }

    private function ehAvaliadorOuAdministrador(AuthUser $authUser, AvaliacaoDocente $avaliacao): bool
    {
        return (int) $avaliacao->avaliador_user_id === (int) $authUser->getAuthIdentifier()
            || $authUser->can('Reabrir:AvaliacaoDocente');
    }
}

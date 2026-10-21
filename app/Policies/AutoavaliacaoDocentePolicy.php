<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AutoavaliacaoDocente;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * A autoavaliação é do professor: só ele a preenche e a envia, e o rascunho é privado. Depois de enviada, a
 * coordenação e a administração passam a vê-la; alterar, só depois de reaberta por quem tem `Reabrir`.
 */
class AutoavaliacaoDocentePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AutoavaliacaoDocente');
    }

    public function view(AuthUser $authUser, AutoavaliacaoDocente $autoavaliacao): bool
    {
        /** @var User $authUser */
        $autoavaliacao->loadMissing('funcionario');

        return $authUser->can('View:AutoavaliacaoDocente')
            && ($autoavaliacao->pertenceA($authUser) || ($autoavaliacao->estaEnviada() && ! $authUser->atuaComoProfessor()));
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AutoavaliacaoDocente');
    }

    public function update(AuthUser $authUser, AutoavaliacaoDocente $autoavaliacao): bool
    {
        /** @var User $authUser */
        $autoavaliacao->loadMissing('funcionario');

        return $authUser->can('Update:AutoavaliacaoDocente')
            && ! $autoavaliacao->estaEnviada()
            && $autoavaliacao->pertenceA($authUser);
    }

    public function enviar(AuthUser $authUser, AutoavaliacaoDocente $autoavaliacao): bool
    {
        /** @var User $authUser */
        $autoavaliacao->loadMissing('funcionario');

        return $authUser->can('Enviar:AutoavaliacaoDocente')
            && ! $autoavaliacao->estaEnviada()
            && $autoavaliacao->pertenceA($authUser);
    }

    public function reabrir(AuthUser $authUser, AutoavaliacaoDocente $autoavaliacao): bool
    {
        return $authUser->can('Reabrir:AutoavaliacaoDocente') && $autoavaliacao->estaEnviada();
    }

    public function delete(AuthUser $authUser, AutoavaliacaoDocente $autoavaliacao): bool
    {
        return false;
    }
}

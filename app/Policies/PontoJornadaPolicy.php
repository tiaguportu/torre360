<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PontoJornada;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Jornada contratada define o que é falta e o que é hora extra: só a administração cadastra. Não se apaga (a mudança
 * de jornada encerra a vigência e abre outra), para não reescrever a apuração do passado.
 */
class PontoJornadaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PontoJornada');
    }

    public function view(AuthUser $authUser, PontoJornada $jornada): bool
    {
        return $authUser->can('View:PontoJornada');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PontoJornada');
    }

    public function update(AuthUser $authUser, PontoJornada $jornada): bool
    {
        return $authUser->can('Update:PontoJornada');
    }

    public function delete(AuthUser $authUser, PontoJornada $jornada): bool
    {
        return false;
    }
}

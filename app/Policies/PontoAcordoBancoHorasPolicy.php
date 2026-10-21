<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PontoAcordoBancoHoras;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Acordo de banco de horas: só a administração cadastra. Não se apaga (encerra-se pela data final), porque os créditos
 * já lançados dependem dele.
 */
class PontoAcordoBancoHorasPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PontoAcordoBancoHoras');
    }

    public function view(AuthUser $authUser, PontoAcordoBancoHoras $acordo): bool
    {
        return $authUser->can('View:PontoAcordoBancoHoras');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PontoAcordoBancoHoras');
    }

    public function update(AuthUser $authUser, PontoAcordoBancoHoras $acordo): bool
    {
        return $authUser->can('Update:PontoAcordoBancoHoras');
    }

    public function delete(AuthUser $authUser, PontoAcordoBancoHoras $acordo): bool
    {
        return false;
    }
}

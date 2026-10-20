<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\InstrumentoAvaliacaoDocente;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class InstrumentoAvaliacaoDocentePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:InstrumentoAvaliacaoDocente');
    }

    public function view(AuthUser $authUser, InstrumentoAvaliacaoDocente $instrumento): bool
    {
        return $authUser->can('View:InstrumentoAvaliacaoDocente');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:InstrumentoAvaliacaoDocente');
    }

    public function update(AuthUser $authUser, InstrumentoAvaliacaoDocente $instrumento): bool
    {
        return $authUser->can('Update:InstrumentoAvaliacaoDocente');
    }

    /**
     * Instrumento usado em ciclos não é apagado: desative-o para sair das listas.
     */
    public function delete(AuthUser $authUser, InstrumentoAvaliacaoDocente $instrumento): bool
    {
        return $authUser->can('Delete:InstrumentoAvaliacaoDocente') && $instrumento->ciclos()->doesntExist();
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:InstrumentoAvaliacaoDocente');
    }
}

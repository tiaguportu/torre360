<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ListaEsperaMatricula;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ListaEsperaMatriculaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ListaEsperaMatricula');
    }

    public function view(AuthUser $authUser, ListaEsperaMatricula $listaEsperaMatricula): bool
    {
        return $authUser->can('View:ListaEsperaMatricula');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ListaEsperaMatricula');
    }

    public function update(AuthUser $authUser, ListaEsperaMatricula $listaEsperaMatricula): bool
    {
        return $authUser->can('Update:ListaEsperaMatricula');
    }

    public function delete(AuthUser $authUser, ListaEsperaMatricula $listaEsperaMatricula): bool
    {
        return $authUser->can('Delete:ListaEsperaMatricula');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ListaEsperaMatricula');
    }
}

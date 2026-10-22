<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ListaMaterialEscolar;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * A secretaria monta e publica; a coordenação consulta. A tela da lista continua abrindo depois de publicada (é de lá
 * que se volta para rascunho, com `publicar`, que cobre as duas direções), mas os itens só mudam em rascunho e a lista
 * publicada não se apaga: as famílias já podem ter comprado o material. A trava de itens vive no `ListaMaterialService`.
 */
class ListaMaterialEscolarPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ListaMaterialEscolar');
    }

    public function view(AuthUser $authUser, ListaMaterialEscolar $lista): bool
    {
        return $authUser->can('View:ListaMaterialEscolar');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ListaMaterialEscolar');
    }

    public function update(AuthUser $authUser, ListaMaterialEscolar $lista): bool
    {
        return $authUser->can('Update:ListaMaterialEscolar');
    }

    public function editarItens(AuthUser $authUser, ListaMaterialEscolar $lista): bool
    {
        return $authUser->can('Update:ListaMaterialEscolar') && ! $lista->estaPublicada();
    }

    public function delete(AuthUser $authUser, ListaMaterialEscolar $lista): bool
    {
        return $authUser->can('Delete:ListaMaterialEscolar') && ! $lista->estaPublicada();
    }

    public function publicar(AuthUser $authUser, ListaMaterialEscolar $lista): bool
    {
        return $authUser->can('Publicar:ListaMaterialEscolar');
    }
}
